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

/**
 * 投稿の作成・編集は、フォーム送信後にいったん画像を合成した状態で確認画面を表示し、
 * 「投稿する」操作で初めてDBへ確定保存する2段階構成とする（項目5：投稿確認画面）。
 * 確認待ちの内容はセッション（$_SESSION['post_pending']）で保持し、画像は
 * uploads/tmp/配下の一時領域に置く。確認せずに投稿フォームを開き直した場合は破棄する。
 */
final class PostController
{
    private const PENDING_SESSION_KEY = 'post_pending';

    public function showCreate(): void
    {
        $user = $this->requirePostableUser();
        $this->clearPending('create');

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

    /**
     * 投稿内容を検証し、画像にロゴを合成したうえで確認画面（post_create_confirm.php）へ進む。
     * この時点ではまだDBへ保存しない。
     */
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

        // 前回確認画面まで進んだが確定・破棄されなかった内容が残っていれば先に片付ける。
        $this->clearPending();

        $token = bin2hex(random_bytes(16));

        try {
            $storedImages = ImageUploader::storeTemp($uploadResult['files'], $token, $logoSettings);
        } catch (\Throwable) {
            ImageUploader::deleteTempDirectory($token);
            $this->renderForm(
                ['画像の処理に失敗しました。もう一度お試しください。'],
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

        $_SESSION[self::PENDING_SESSION_KEY] = [
            'token' => $token,
            'mode' => 'create',
            'user_id' => $user->id,
            'post_id' => null,
            'post_type' => $postType,
            'title' => $postTitle,
            'body' => $body,
            'status' => $status,
            'selected_tag_ids' => $selectedTagIds,
            'new_tag_names' => $newTagNames,
            'delete_image_ids' => [],
            'images' => $storedImages,
        ];

        header('Location: /post_create_confirm.php');
        exit;
    }

    public function showCreateConfirm(): void
    {
        $user = $this->requirePostableUser();
        $pending = $this->requirePending('create', $user);

        View::render('post_create_confirm', [
            'title' => '投稿内容の確認',
            'wide' => true,
            'pending' => $pending,
            'postTypeLabel' => $pending['post_type'] === 'official_blog' ? 'サークル公式ブログ' : '個人の作品記事',
            'statusLabel' => $pending['status'] === 'published' ? '公開' : '下書き',
            'tagNames' => $this->resolveTagNames($pending['selected_tag_ids'], $pending['new_tag_names']),
        ]);
    }

    /**
     * 確認画面からの「投稿する／下書き保存する」「修正する」操作を受け付け、確定時のみDBへ保存する。
     */
    public function confirmCreate(): void
    {
        $user = $this->requirePostableUser();
        $pending = $this->requirePending('create', $user);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            header('Location: /post_create_confirm.php');
            exit;
        }

        $confirmAction = (string) ($_POST['confirm_action'] ?? '');

        if ($confirmAction === 'cancel') {
            $this->clearPending();
            header('Location: /post_create.php');
            exit;
        }

        if ($confirmAction !== 'commit') {
            header('Location: /post_create_confirm.php');
            exit;
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $post = Post::create($user->id, $pending['post_type'], $pending['title'], $pending['body'], $pending['status']);

            $finalImages = ImageUploader::moveTempToPost($pending['images'], $post->id);
            foreach ($finalImages as $index => $image) {
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

            $tagIds = $pending['selected_tag_ids'];
            foreach ($pending['new_tag_names'] as $tagName) {
                $tagIds[] = Tag::findOrCreateByName($tagName);
            }
            if (!empty($tagIds)) {
                Tag::attachToPost($post->id, $tagIds);
            }

            $connection->commit();
        } catch (\Throwable) {
            $connection->rollBack();

            if (isset($post)) {
                ImageUploader::deletePostDirectory($post->id);
            }
            $this->clearPending();

            $this->renderForm(
                ['投稿の保存に失敗しました。もう一度お試しください。'],
                null,
                '',
                '',
                '',
                [],
                Tag::findAll(),
                $user,
                'individual'
            );
            return;
        }

        $this->clearPending();

        $notice = $pending['status'] === 'published' ? '作品を公開しました。' : '下書きとして保存しました。';
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
        [$post, $user] = $this->requirePostAccess();
        $this->clearPending('edit');

        $this->renderEditForm(
            $post,
            [],
            null,
            $post->title,
            $post->body,
            Tag::findIdsByPostId($post->id),
            '',
            $user
        );
    }

    /**
     * 投稿内容を検証し、新規画像にロゴを合成したうえで確認画面（post_edit_confirm.php）へ進む。
     * この時点ではまだDBへ反映しない（既存画像の削除も含む）。
     */
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

        // 既存画像の削除（休止会員は不可。本人・管理者ともここでの「本人」は休止会員なら除外される）。
        $deleteImageIds = [];
        if ($this->canDeleteImages($user)) {
            $requestedDeleteIds = array_map('intval', (array) ($_POST['delete_image_ids'] ?? []));
            $existingImageIds = array_column($existingImages, 'id');
            $deleteImageIds = array_values(array_intersect($requestedDeleteIds, $existingImageIds));
        }

        $deletedBytes = 0;
        foreach ($existingImages as $image) {
            if (in_array($image['id'], $deleteImageIds, true)) {
                $deletedBytes += $image['file_size_kb'] * 1024;
            }
        }
        $remainingExistingCount = $existingCount - count($deleteImageIds);
        $remainingExistingBytes = $existingBytes - $deletedBytes;

        $uploadResult = ImageUploader::validate($_FILES['images'] ?? []);
        $errors = array_merge($errors, $uploadResult['errors']);
        $errors = array_merge(
            $errors,
            ImageUploader::checkAggregateLimits($uploadResult['files'], $remainingExistingCount, $remainingExistingBytes)
        );

        // ロゴ調整フォーム（FR-19、JSにより画像ごとに動的生成）の値。アップロードと同じ並び順。
        $logoSettings = ImageUploader::parseLogoSettings(
            (array) ($_POST['logo_pos_x'] ?? []),
            (array) ($_POST['logo_pos_y'] ?? []),
            (array) ($_POST['logo_scale'] ?? []),
            (array) ($_POST['logo_opacity'] ?? []),
            count($uploadResult['files'])
        );

        // 公開時のみ本文・画像を必須とする（FR-05）。削除後に残る画像があれば新規追加は不要。
        if ($status === 'published') {
            if ($body === '') {
                $errors[] = '本文を入力してください。';
            }
            if ($remainingExistingCount === 0 && $uploadResult['files'] === [] && $uploadResult['errors'] === []) {
                $errors[] = '画像を1枚以上アップロードしてください。';
            }
        }

        $existingTags = Tag::findAll();
        $existingTagIds = array_column($existingTags, 'id');
        $selectedTagIds = array_values(array_intersect($selectedTagIds, $existingTagIds));

        $newTagNames = $this->parseTagNames($newTagsRaw);

        if (!empty($errors)) {
            $this->renderEditForm($post, $errors, null, $postTitle, $body, $selectedTagIds, $newTagsRaw, $user);
            return;
        }

        $this->clearPending();

        $token = bin2hex(random_bytes(16));

        try {
            $storedImages = ImageUploader::storeTemp($uploadResult['files'], $token, $logoSettings);
        } catch (\Throwable) {
            ImageUploader::deleteTempDirectory($token);
            $this->renderEditForm(
                $post,
                ['画像の処理に失敗しました。もう一度お試しください。'],
                null,
                $postTitle,
                $body,
                $selectedTagIds,
                $newTagsRaw,
                $user
            );
            return;
        }

        $_SESSION[self::PENDING_SESSION_KEY] = [
            'token' => $token,
            'mode' => 'edit',
            'user_id' => $user->id,
            'post_id' => $post->id,
            'post_type' => $post->postType,
            'title' => $postTitle,
            'body' => $body,
            'status' => $status,
            'selected_tag_ids' => $selectedTagIds,
            'new_tag_names' => $newTagNames,
            'delete_image_ids' => $deleteImageIds,
            'images' => $storedImages,
        ];

        header('Location: /post_edit_confirm.php?id=' . $post->id);
        exit;
    }

    public function showEditConfirm(): void
    {
        [$post, $user] = $this->requirePostAccess();
        $pending = $this->requirePending('edit', $user, $post->id);

        $remainingExistingImages = array_values(array_filter(
            Image::findByPostId($post->id),
            static fn (array $image): bool => !in_array($image['id'], $pending['delete_image_ids'], true)
        ));

        View::render('post_edit_confirm', [
            'title' => '投稿内容の確認',
            'wide' => true,
            'post' => $post,
            'pending' => $pending,
            'remainingExistingImages' => $remainingExistingImages,
            'deletedCount' => count($pending['delete_image_ids']),
            'statusLabel' => $pending['status'] === 'published' ? '公開' : '下書き',
            'tagNames' => $this->resolveTagNames($pending['selected_tag_ids'], $pending['new_tag_names']),
        ]);
    }

    /**
     * 確認画面からの「投稿する／下書き保存する」「修正する」操作を受け付け、確定時のみDBへ反映する。
     */
    public function confirmEdit(): void
    {
        [$post, $user] = $this->requirePostAccess();
        $pending = $this->requirePending('edit', $user, $post->id);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            header('Location: /post_edit_confirm.php?id=' . $post->id);
            exit;
        }

        $confirmAction = (string) ($_POST['confirm_action'] ?? '');

        if ($confirmAction === 'cancel') {
            $this->clearPending();
            header('Location: /post_edit.php?id=' . $post->id);
            exit;
        }

        if ($confirmAction !== 'commit') {
            header('Location: /post_edit_confirm.php?id=' . $post->id);
            exit;
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            foreach ($pending['delete_image_ids'] as $imageId) {
                $image = Image::findById($imageId);
                if ($image !== null && $image['post_id'] === $post->id) {
                    $this->deleteImageFiles($image);
                    Image::deleteById($imageId);
                }
            }

            $post->update($pending['title'], $pending['body'], $pending['status']);

            $finalImages = ImageUploader::moveTempToPost($pending['images'], $post->id);
            $nextOrder = Image::nextSortOrder($post->id);
            foreach ($finalImages as $index => $image) {
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
            $tagIds = $pending['selected_tag_ids'];
            foreach ($pending['new_tag_names'] as $tagName) {
                $tagIds[] = Tag::findOrCreateByName($tagName);
            }
            if (!empty($tagIds)) {
                Tag::attachToPost($post->id, $tagIds);
            }

            $connection->commit();
        } catch (\Throwable) {
            $connection->rollBack();
            $this->clearPending();

            $this->renderEditForm(
                $post,
                ['投稿の更新に失敗しました。もう一度お試しください。'],
                null,
                $post->title,
                $post->body,
                Tag::findIdsByPostId($post->id),
                '',
                $user
            );
            return;
        }

        $this->clearPending();

        $notice = $pending['status'] === 'published' ? '作品を公開しました。' : '下書きとして保存しました。';
        $this->renderEditForm($post, [], $notice, $post->title, $post->body, Tag::findIdsByPostId($post->id), '', $user);
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
     * 既存投稿の画像削除は休止会員のみ不可とする（新規投稿・プロフィール編集不可のFR-11とは別に、
     * 画像削除に限定した制約として追加）。休止会員以外（本人が会員/広報担当、または管理者）は可能。
     */
    private function canDeleteImages(User $user): bool
    {
        return $user->role !== 'inactive';
    }

    /**
     * 確認待ちの投稿内容をセッションから取得する。対象が無い・別モード・別ユーザー・別投稿の
     * ものであれば、投稿フォームへ差し戻す。
     *
     * @return array{token: string, mode: string, user_id: int, post_id: ?int, post_type: string,
     *     title: string, body: string, status: string, selected_tag_ids: array<int, int>,
     *     new_tag_names: array<int, string>, delete_image_ids: array<int, int>,
     *     images: array<int, array{original_path: string, display_path: string, file_size_kb: int,
     *     logo_pos_x: float, logo_pos_y: float, logo_scale: float, logo_opacity: float}>}
     */
    private function requirePending(string $mode, User $user, ?int $postId = null): array
    {
        $pending = $_SESSION[self::PENDING_SESSION_KEY] ?? null;

        $valid = is_array($pending)
            && ($pending['mode'] ?? null) === $mode
            && ($pending['user_id'] ?? null) === $user->id
            && ($postId === null || ($pending['post_id'] ?? null) === $postId);

        if (!$valid) {
            $redirectTo = ($mode === 'edit' && $postId !== null)
                ? '/post_edit.php?id=' . $postId
                : '/post_create.php';
            header('Location: ' . $redirectTo);
            exit;
        }

        return $pending;
    }

    /**
     * 確認待ちの内容（あれば）を、一時画像ごと破棄する。$modeを指定した場合はそのモードの
     * 確認待ちのみを対象とする（例：新規投稿フォームを開いた際に新規投稿分だけ破棄する）。
     */
    private function clearPending(?string $mode = null): void
    {
        $pending = $_SESSION[self::PENDING_SESSION_KEY] ?? null;
        if (!is_array($pending)) {
            return;
        }
        if ($mode !== null && ($pending['mode'] ?? null) !== $mode) {
            return;
        }

        if (!empty($pending['token'])) {
            ImageUploader::deleteTempDirectory($pending['token']);
        }
        unset($_SESSION[self::PENDING_SESSION_KEY]);
    }

    /**
     * 確認画面表示用に、選択済み既存タグのIDを名前へ、新規タグはそのまま表示名の配列にまとめる。
     *
     * @param array<int, int> $selectedTagIds
     * @param array<int, string> $newTagNames
     * @return array<int, string>
     */
    private function resolveTagNames(array $selectedTagIds, array $newTagNames): array
    {
        $names = [];

        if (!empty($selectedTagIds)) {
            foreach (Tag::findAll() as $tag) {
                if (in_array($tag['id'], $selectedTagIds, true)) {
                    $names[] = $tag['name'];
                }
            }
        }

        foreach ($newTagNames as $name) {
            $names[] = $name . '（新規）';
        }

        return $names;
    }

    /**
     * @param array{original_path: string, display_path: string} $image
     */
    private function deleteImageFiles(array $image): void
    {
        foreach (['original_path', 'display_path'] as $key) {
            $path = $image[$key] ?? '';
            if ($path === '') {
                continue;
            }

            $fullPath = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
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
        string $formNewTags,
        User $user
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
            'canDeleteImages' => $this->canDeleteImages($user),
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
