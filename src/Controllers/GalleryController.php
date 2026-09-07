<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Image;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;

/**
 * 訪問者・会員登録なしでも閲覧できる公開ページ（FR-15）。
 * 下書きは一切表示しない。
 */
final class GalleryController
{
    private const HOME_LIMIT = 5;
    private const TAG_INITIAL_DISPLAY_LIMIT = 25;

    public function showHome(): void
    {
        View::render('home', [
            'title' => '九産模型愛好会',
            'wide' => true,
            'individualPosts' => $this->decorate(Post::findPublishedByType('individual', self::HOME_LIMIT)),
            'blogPosts' => $this->decorate(Post::findPublishedByType('official_blog', self::HOME_LIMIT)),
        ]);
    }

    public function showGallery(): void
    {
        View::render('gallery', [
            'title' => '作品ギャラリー',
            'wide' => true,
            'posts' => $this->decorate(Post::findPublishedByType('individual')),
        ]);
    }

    public function showBlog(): void
    {
        View::render('blog', [
            'title' => '公式ブログ',
            'wide' => true,
            'posts' => $this->decorate(Post::findPublishedByType('official_blog')),
        ]);
    }

    /**
     * タグ別一覧・複数タグ絞り込み（FR-18）。個人作品・公式ブログを1つの一覧にまとめて表示する。
     * 選択タグは全て一致するもの（AND条件）のみを表示する。
     */
    public function showTags(): void
    {
        $tagIds = array_map('intval', (array) ($_GET['tag_ids'] ?? []));
        $tagIds = array_values(array_unique(array_filter($tagIds, static fn (int $id): bool => $id > 0)));

        $allTags = Tag::findAllWithPublishedPostCount();
        $topTags = array_slice($allTags, 0, self::TAG_INITIAL_DISPLAY_LIMIT);
        $moreTags = array_slice($allTags, self::TAG_INITIAL_DISPLAY_LIMIT);

        // 「もっと見る」の外側にあるタグが選択済みの場合は、選択内容が見える状態で開いておく。
        $moreTagIds = array_column($moreTags, 'id');
        $expandMoreTags = array_intersect($tagIds, $moreTagIds) !== [];

        View::render('tags', [
            'title' => 'タグから探す',
            'wide' => true,
            'topTags' => $topTags,
            'moreTags' => $moreTags,
            'expandMoreTags' => $expandMoreTags,
            'selectedTagIds' => $tagIds,
            'posts' => empty($tagIds) ? [] : $this->decorate(Post::findPublishedByTagIds($tagIds)),
        ]);
    }

    public function showPost(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $post = $id > 0 ? Post::findPublishedById($id) : null;

        if ($post === null) {
            View::render('post_not_found', ['title' => '投稿が見つかりません']);
            return;
        }

        $author = User::findById($post->userId);

        View::render('post_view', [
            'title' => $post->title,
            'wide' => true,
            'post' => $post,
            'authorName' => $author?->displayName ?? '(退会した会員)',
            'images' => Image::findByPostId($post->id),
            'tags' => Tag::findByPostId($post->id),
        ]);
    }

    /**
     * @param array<int, Post> $posts
     * @return array<int, array{post: Post, authorName: string, thumbnail: ?string}>
     */
    private function decorate(array $posts): array
    {
        $decorated = [];
        foreach ($posts as $post) {
            $author = User::findById($post->userId);
            $images = Image::findByPostId($post->id);

            $decorated[] = [
                'post' => $post,
                'authorName' => $author?->displayName ?? '(退会した会員)',
                'thumbnail' => $images[0]['display_path'] ?? null,
            ];
        }

        return $decorated;
    }
}
