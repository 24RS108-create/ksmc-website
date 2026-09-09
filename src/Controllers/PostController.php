<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\ImageUploader;
use App\Core\View;
use App\Models\Image;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;

final class PostController
{
    public function showCreate(): void
    {
        $user = $this->requirePostableUser();

        View::render('post_create', [
            'title' => '作品投稿',
            'errors' => [],
            'notice' => null,
            'formTitle' => '',
            'formBody' => '',
            'formNewTags' => '',
            'selectedTagIds' => [],
            'tags' => Tag::findAll(),
            'canPostOfficialBlog' => $this->canPostOfficialBlog($user),
            'formPostType' => 'individual',
        ]);
    }

    public function create(): void
    {
        $user = $this->requirePostableUser();

        $action = (string) ($_POST['action'] ?? '');
        $status = $action === 'publish' ? 'published' : 'draft';

        $postType = (string) ($_POST['post_type'] ?? 'individual');
        $postTitle = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $newTagsRaw = (string) ($_POST['new_tags'] ?? '');
        $selectedTagIds = array_map('intval', (array) ($_POST['tag_ids'] ?? []));
        $errors = [];

        if (!in_array($action, ['draft', 'publish'], true) || !Csrf::verify($_POST['csrf_token'] ?? null)) {
            $errors[] = '不正なリクエストです。もう一度お試しください。';
        }
        if (!in_array($postType, ['individual', 'official_blog'], true)) {
            $postType = 'individual';
        }
        if ($postType === 'official_blog' && !$this->canPostOfficialBlog($user)) {
            $errors[] = '公式ブログへの投稿権限がありません。';
            $postType = 'individual';
        }
        if ($postTitle === '') {
            $errors[] = 'タイトルを入力してください。';
        } elseif (mb_strlen($postTitle) > 200) {
            $errors[] = 'タイトルは200文字以内で入力してください。';
        }

        $uploadResult = ImageUploader::validate($_FILES['images'] ?? []);
        $errors = array_merge($errors, $uploadResult['errors']);
        $errors = array_merge($errors, ImageUploader::checkAggregateLimits($uploadResult['files']));

        // ロゴ調整フォーム（FR-19、JSにより画像ごとに動的生成）の値。アップロードと同じ並び順。
        $logoSettings = ImageUploader::parseLogoSettings(
            (array) ($_POST['logo_pos_x'] ?? []),
            (array) ($_POST['logo_pos_y'] ?? []),
            (array) ($_POST['logo_scale'] ?? []),
            (array) ($_POST['logo_opacity'] ?? []),
            count($uploadResult['files'])
        );

        // 公開時のみ本文・画像を必須とする。下書きは未完成のまま保存できる（FR-05）。
        if ($status === 'published') {
            if ($body === '') {
                $errors[] = '本文を入力してください。';
            }
            if ($uploadResult['files'] === [] && $uploadResult['errors'] === []) {
                $errors[] = '画像を1枚以上アップロードしてください。';
            }
        }

        $existingTags = Tag::findAll();
        $existingTagIds = array_column($existingTags, 'id');
        $selectedTagIds = array_values(array_intersect($selectedTagIds, $existingTagIds));

        $newTagNames = $this->parseTagNames($newTagsRaw);

        if (!empty($errors)) {
            $this->renderForm($errors, null, $postTitle, $body, $newTagsRaw, $selectedTagIds, $existingTags, $user, $postType);
            return;
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $post = Post::create($user->id, $postType, $postTitle, $body, $status);

            $storedImages = ImageUploader::store($uploadResult['files'], $post->id, $logoSettings);
            foreach ($storedImages as $index => $image) {
                Image::create(
                    $post->id,
                    $image['original_path'],
                    $image['display_path'],
                    $image['file_size_kb'],
                    $index,
                    $image['logo_pos_x'],
                    $image['logo_pos_y'],
                    $image['logo_scale'],
                    $image['logo_opacity']
                );
            }

            $tagIds = $selectedTagIds;
            foreach ($newTagNames as $tagName) {
                $tagIds[] = Tag::findOrCreateByName($tagName);
            }
            if (!empty($tagIds)) {
                Tag::attachToPost($post->id, $tagIds);
            }

            $connection->commit();
        } catch (\Throwable) {
            $connection->rollBack();

            // DBはロールバックされるが、アップロード先ディレクトリはファイルシステム操作のため
            // 別途削除する（ロゴ合成失敗時等に空ディレクトリが残るのを防ぐ）。
            if (isset($post)) {
                ImageUploader::deletePostDirectory($post->id);
            }

            $this->renderForm(
                ['投稿の保存に失敗しました。もう一度お試しください。'],
                null,
                $postTitle,
                $body,
                $newTagsRaw,
                $selectedTagIds,
                $existingTags,
                $user,
                $postType
            );
            return;
        }

        $notice = $status === 'published' ? '作品を公開しました。' : '下書きとして保存しました。';
        $this->renderForm([], $notice, '', '', '', [], Tag::findAll(), $user, 'individual');
    }

    public function showMyPosts(): void
    {
        $user = Auth::requireLogin();

        View::render('my_posts', [
            'title' => '自分の投稿',
            'posts' => Post::findByUserId($user->id),
        ]);
    }

    public function showEdit(): void
    {
        [$post] = $this->requirePostAccess();

        $this->renderEditForm(
            $post,
            [],
            null,
            $post->title,
            $post->body,
            Tag::findIdsByPostId($post->id)
        );
    }

    public function update(): void
    {
        [$post, $user] = $this->requirePostAccess();

        $action = (string) ($_POST['action'] ?? '');
        $status = $action === 'publish' ? 'published' : 'draft';

        $postTitle = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $newTagsRaw = (string) ($_POST['new_tags'] ?? '');
        $selectedTagIds = array_map('intval', (array) ($_POST['tag_ids'] ?? []));
        $errors = [];

        if (!in_array($action, ['draft', 'publish'], true) || !Csrf::verify($_POST['csrf_token'] ?? null)) {
            $errors[] = '不正なリクエストです。もう一度お試しください。';
        }
        if ($postTitle === '') {
            $errors[] = 'タイトルを入力してください。';
        } elseif (mb_strlen($postTitle) > 200) {
            $errors[] = 'タイトルは200文字以内で入力してください。';
        }

        $existingImages = Image::findByPostId($post->id);
        $existingCount = count($existingImages);
        $existingBytes = array_sum(array_column($existingImages, 'file_size_kb')) * 1024;

        $uploadResult = ImageUploader::validate($_FILES['images'] ?? []);
        $errors = array_merge($errors, $uploadResult['errors']);
        $errors = array_merge(
            $errors,
            ImageUploader::checkAggregateLimits($uploadResult['files'], $existingCount, $existingBytes)
        );

        // ロゴ調整フォーム（FR-19、JSにより画像ごとに動的生成）の値。アップロードと同じ並び順。
        $logoSettings = ImageUploader::parseLogoSettings(
            (array) ($_POST['logo_pos_x'] ?? []),
            (array) ($_POST['logo_pos_y'] ?? []),
            (array) ($_POST['logo_scale'] ?? []),
            (array) ($_POST['logo_opacity'] ?? []),
            count($uploadResult['files'])
        );

        // 公開時のみ本文・画像を必須とする（FR-05）。画像は既存分があれば新規追加は不要。
        if ($status === 'published') {
            if ($body === '') {
                $errors[] = '本文を入力してください。';
            }
            if ($existingCount === 0 && $uploadResult['files'] === [] && $uploadResult['errors'] === []) {
                $errors[] = '画像を1枚以上アップロードしてください。';
            }
        }

        $existingTags = Tag::findAll();
        $existingTagIds = array_column($existingTags, 'id');
        $selectedTagIds = array_values(array_intersect($selectedTagIds, $existingTagIds));

        $newTagNames = $this->parseTagNames($newTagsRaw);

        if (!empty($errors)) {
            $this->renderEditForm($post, $errors, null, $postTitle, $body, $selectedTagIds, $newTagsRaw);
            return;
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $post->update($postTitle, $body, $status);

            $storedImages = ImageUploader::store($uploadResult['files'], $post->id, $logoSettings);
            $nextOrder = Image::nextSortOrder($post->id);
            foreach ($storedImages as $index => $image) {
                Image::create(
                    $post->id,
                    $image['original_path'],
                    $image['display_path'],
                    $image['file_size_kb'],
                    $nextOrder + $index,
                    $image['logo_pos_x'],
                    $image['logo_pos_y'],
                    $image['logo_scale'],
                    $image['logo_opacity']
                );
            }

            Tag::detachAllFromPost($post->id);
            $tagIds = $selectedTagIds;
            foreach ($newTagNames as $tagName) {
                $tagIds[] = Tag::findOrCreateByName($tagName);
            }
            if (!empty($tagIds)) {
                Tag::attachToPost($post->id, $tagIds);
            }

            $connection->commit();
        } catch (\Throwable) {
            $connection->rollBack();

            $this->renderEditForm(
                $post,
                ['投稿の更新に失敗しました。もう一度お試しください。'],
                null,
                $postTitle,
                $body,
                $selectedTagIds,
                $newTagsRaw
            );
            return;
        }

        $notice = $status === 'published' ? '作品を公開しました。' : '下書きとして保存しました。';
        $this->renderEditForm($post, [], $notice, $post->title, $post->body, Tag::findIdsByPostId($post->id));
    }

    public function showDeleteConfirm(): void
    {
        [$post] = $this->requirePostAccess();

        View::render('post_delete_confirm', [
            'title' => '投稿の削除確認',
            'post' => $post,
        ]);
    }

    public function delete(): void
    {
        [$post] = $this->requirePostAccess();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            View::render('post_delete_confirm', [
                'title' => '投稿の削除確認',
                'post' => $post,
                'error' => '不正なリクエストです。もう一度お試しください。',
            ]);
            return;
        }

        Post::delete($post->id);
        ImageUploader::deletePostDirectory($post->id);

        header('Location: /my_posts.php');
        exit;
    }

    private function requirePostableUser(): User
    {
        $user = Auth::requireLogin();

        // 休止会員は新規投稿不可（FR-11）。プロフィール編集も不可のため、自分の投稿一覧へ誘導する。
        if ($user->role === 'inactive') {
            header('Location: /my_posts.php');
            exit;
        }

        return $user;
    }

    /**
     * ログイン済みで、投稿の所有者本人または管理者であることを確認する（FR-06）。
     * 休止会員でも自分の既存投稿は編集・削除できる（FR-11）。
     *
     * @return array{0: Post, 1: User}
     */
    private function requirePostAccess(): array
    {
        $user = Auth::requireLogin();

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $post = $id > 0 ? Post::findById($id) : null;

        if ($post === null) {
            header('Location: /my_posts.php');
            exit;
        }

        if ($post->userId !== $user->id && $user->role !== 'admin') {
            header('Location: /my_posts.php');
            exit;
        }

        return [$post, $user];
    }

    /**
     * @param array<int, string> $errors
     * @param array<int, int> $selectedTagIds
     * @param array<int, array{id: int, name: string}> $tags
     */
    private function renderForm(
        array $errors,
        ?string $notice,
        string $formTitle,
        string $formBody,
        string $formNewTags,
        array $selectedTagIds,
        array $tags,
        User $user,
        string $formPostType
    ): void {
        View::render('post_create', [
            'title' => '作品投稿',
            'errors' => $errors,
            'notice' => $notice,
            'formTitle' => $formTitle,
            'formBody' => $formBody,
            'formNewTags' => $formNewTags,
            'selectedTagIds' => $selectedTagIds,
            'tags' => $tags,
            'canPostOfficialBlog' => $this->canPostOfficialBlog($user),
            'formPostType' => $formPostType,
        ]);
    }

    /**
     * 公式ブログへの投稿権限は広報担当と管理者に限定する（FR-14）。
     */
    private function canPostOfficialBlog(User $user): bool
    {
        return in_array($user->role, ['pr', 'admin'], true);
    }

    /**
     * @param array<int, string> $errors
     * @param array<int, int> $selectedTagIds
     */
    private function renderEditForm(
        Post $post,
        array $errors,
        ?string $notice,
        string $formTitle,
        string $formBody,
        array $selectedTagIds,
        string $formNewTags = ''
    ): void {
        View::render('post_edit', [
            'title' => '投稿を編集',
            'errors' => $errors,
            'notice' => $notice,
            'post' => $post,
            'formTitle' => $formTitle,
            'formBody' => $formBody,
            'formNewTags' => $formNewTags,
            'selectedTagIds' => $selectedTagIds,
            'tags' => Tag::findAll(),
            'existingImages' => Image::findByPostId($post->id),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function parseTagNames(string $raw): array
    {
        $parts = preg_split('/[,\n\r]+/u', $raw) ?: [];
        $names = [];

        foreach ($parts as $part) {
            $name = trim($part);
            if ($name === '' || mb_strlen($name) > 50) {
                continue;
            }
            $names[] = $name;
        }

        return array_values(array_unique($names));
    }
}
