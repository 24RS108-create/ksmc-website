<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Paginator;
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
        $pagination = Paginator::resolve(
            $this->requestedPage(),
            Post::countPublishedByType('individual')
        );

        View::render('gallery', [
            'title' => '作品ギャラリー',
            'wide' => true,
            'posts' => $this->decorate(Post::findPublishedByType('individual', $pagination['perPage'], $pagination['offset'])),
            'pagination' => $pagination,
            'pageBaseUrl' => '/gallery.php',
            'extraQuery' => [],
        ]);
    }

    public function showBlog(): void
    {
        $pagination = Paginator::resolve(
            $this->requestedPage(),
            Post::countPublishedByType('official_blog')
        );

        View::render('blog', [
            'title' => '公式ブログ',
            'wide' => true,
            'posts' => $this->decorate(Post::findPublishedByType('official_blog', $pagination['perPage'], $pagination['offset'])),
            'pagination' => $pagination,
            'pageBaseUrl' => '/blog.php',
            'extraQuery' => [],
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

        $pagination = Paginator::resolve(
            $this->requestedPage(),
            empty($tagIds) ? 0 : Post::countPublishedByTagIds($tagIds)
        );

        View::render('tags', [
            'title' => 'タグから探す',
            'wide' => true,
            'topTags' => $topTags,
            'moreTags' => $moreTags,
            'expandMoreTags' => $expandMoreTags,
            'selectedTagIds' => $tagIds,
            'posts' => empty($tagIds) ? [] : $this->decorate(Post::findPublishedByTagIds($tagIds, $pagination['perPage'], $pagination['offset'])),
            'pagination' => $pagination,
            'pageBaseUrl' => '/tags.php',
            'extraQuery' => ['tag_ids' => $tagIds],
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
            'authorId' => $author?->id,
            'authorName' => $author?->displayName ?? '(退会した会員)',
            'images' => Image::findByPostId($post->id),
            'tags' => Tag::findByPostId($post->id),
        ]);
    }

    /**
     * 会員ページ（FR-28）。指定した会員の表示名と公開済み投稿一覧を表示する。
     * プロフィールメモ（profile_note）は非公開項目のため表示しない。
     */
    public function showMember(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $member = $id > 0 ? User::findById($id) : null;

        if ($member === null) {
            View::render('member_not_found', ['title' => '会員が見つかりません']);
            return;
        }

        $pagination = Paginator::resolve(
            $this->requestedPage(),
            Post::countPublishedByUserId($member->id)
        );

        View::render('member', [
            'title' => $member->displayName,
            'wide' => true,
            'member' => $member,
            'posts' => $this->decorate(Post::findPublishedByUserId($member->id, $pagination['perPage'], $pagination['offset'])),
            'pagination' => $pagination,
            'pageBaseUrl' => '/member.php',
            'extraQuery' => ['id' => $member->id],
        ]);
    }

    /**
     * クエリパラメータpageを読み取る。不正な値（数値でない・0以下等）は1として扱う
     * （Paginator::resolve()側でも範囲チェックするため、ここでは大まかな検証のみ）。
     */
    private function requestedPage(): int
    {
        $page = (int) ($_GET['page'] ?? 1);

        return $page > 0 ? $page : 1;
    }

    /**
     * @param array<int, Post> $posts
     * @return array<int, array{post: Post, authorId: ?int, authorName: string, thumbnail: ?string}>
     */
    private function decorate(array $posts): array
    {
        $decorated = [];
        foreach ($posts as $post) {
            $author = User::findById($post->userId);
            $images = Image::findByPostId($post->id);

            $decorated[] = [
                'post' => $post,
                'authorId' => $author?->id,
                'authorName' => $author?->displayName ?? '(退会した会員)',
                'thumbnail' => $images[0]['display_path'] ?? null,
            ];
        }

        return $decorated;
    }
}
