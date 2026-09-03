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
        $this->requirePostableUser();

        View::render('post_create', [
            'title' => '作品投稿',
            'errors' => [],
            'notice' => null,
            'formTitle' => '',
            'formBody' => '',
            'formNewTags' => '',
            'selectedTagIds' => [],
            'tags' => Tag::findAll(),
        ]);
    }

    public function create(): void
    {
        $user = $this->requirePostableUser();

        $postTitle = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $newTagsRaw = (string) ($_POST['new_tags'] ?? '');
        $selectedTagIds = array_map('intval', (array) ($_POST['tag_ids'] ?? []));
        $errors = [];

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $errors[] = '不正なリクエストです。もう一度お試しください。';
        }
        if ($postTitle === '') {
            $errors[] = 'タイトルを入力してください。';
        } elseif (mb_strlen($postTitle) > 200) {
            $errors[] = 'タイトルは200文字以内で入力してください。';
        }
        if ($body === '') {
            $errors[] = '本文を入力してください。';
        }

        $uploadResult = ImageUploader::validate($_FILES['images'] ?? []);
        if ($uploadResult['files'] === [] && $uploadResult['errors'] === []) {
            $errors[] = '画像を1枚以上アップロードしてください。';
        }
        $errors = array_merge($errors, $uploadResult['errors']);

        $existingTags = Tag::findAll();
        $existingTagIds = array_column($existingTags, 'id');
        $selectedTagIds = array_values(array_intersect($selectedTagIds, $existingTagIds));

        $newTagNames = $this->parseTagNames($newTagsRaw);

        if (!empty($errors)) {
            View::render('post_create', [
                'title' => '作品投稿',
                'errors' => $errors,
                'notice' => null,
                'formTitle' => $postTitle,
                'formBody' => $body,
                'formNewTags' => $newTagsRaw,
                'selectedTagIds' => $selectedTagIds,
                'tags' => $existingTags,
            ]);
            return;
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $post = Post::createPublishedIndividual($user->id, $postTitle, $body);

            $storedImages = ImageUploader::store($uploadResult['files'], $post->id);
            foreach ($storedImages as $index => $image) {
                Image::create($post->id, $image['display_path'], $image['file_size_kb'], $index);
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

            View::render('post_create', [
                'title' => '作品投稿',
                'errors' => ['投稿の保存に失敗しました。もう一度お試しください。'],
                'notice' => null,
                'formTitle' => $postTitle,
                'formBody' => $body,
                'formNewTags' => $newTagsRaw,
                'selectedTagIds' => $selectedTagIds,
                'tags' => $existingTags,
            ]);
            return;
        }

        View::render('post_create', [
            'title' => '作品投稿',
            'errors' => [],
            'notice' => '作品を投稿しました。',
            'formTitle' => '',
            'formBody' => '',
            'formNewTags' => '',
            'selectedTagIds' => [],
            'tags' => Tag::findAll(),
        ]);
    }

    private function requirePostableUser(): User
    {
        $user = Auth::requireLogin();

        // 休止会員は新規投稿不可（FR-11）。
        if ($user->role === 'inactive') {
            header('Location: /profile_edit.php');
            exit;
        }

        return $user;
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
