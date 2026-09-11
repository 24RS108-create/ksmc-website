<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Models\Tag;

/**
 * 管理者によるタグの統合・リネーム機能（FR-17）。表記ゆれ対策。
 */
final class TagController
{
    public function showManage(): void
    {
        Auth::requireRole('admin');

        View::render('tag_manage', [
            'title' => 'タグ管理',
            'notice' => null,
            'error' => null,
            'tags' => Tag::findAllWithPostCount(),
        ]);
    }

    public function rename(): void
    {
        Auth::requireRole('admin');

        $id = (int) ($_POST['tag_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $this->renderManage('不正なリクエストです。もう一度お試しください。', null);
            return;
        }

        $target = $id > 0 ? Tag::findById($id) : null;
        if ($target === null) {
            $this->renderManage('対象のタグが見つかりませんでした。', null);
            return;
        }
        if ($name === '') {
            $this->renderManage('新しいタグ名を入力してください。', null);
            return;
        }
        if (mb_strlen($name) > 50) {
            $this->renderManage('タグ名は50文字以内で入力してください。', null);
            return;
        }
        if ($name === $target['name']) {
            $this->renderManage(null, "「{$target['name']}」から変更がありませんでした。");
            return;
        }
        if (Tag::nameExists($name, $id)) {
            $this->renderManage('そのタグ名は既に使用されています。統合機能をご利用ください。', null);
            return;
        }

        Tag::rename($id, $name);
        $this->renderManage(null, "「{$target['name']}」を「{$name}」に変更しました。");
    }

    public function merge(): void
    {
        Auth::requireRole('admin');

        $targetId = (int) ($_POST['target_tag_id'] ?? 0);
        $sourceIds = array_map('intval', (array) ($_POST['source_tag_ids'] ?? []));
        $sourceIds = array_values(array_filter($sourceIds, static fn (int $id): bool => $id !== $targetId));

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $this->renderManage('不正なリクエストです。もう一度お試しください。', null);
            return;
        }

        $target = $targetId > 0 ? Tag::findById($targetId) : null;
        if ($target === null) {
            $this->renderManage('統合先のタグを選択してください。', null);
            return;
        }
        if (empty($sourceIds)) {
            $this->renderManage('統合元のタグを1つ以上選択してください（統合先自身は選択できません）。', null);
            return;
        }

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            Tag::mergeInto($sourceIds, $targetId);
            $connection->commit();
        } catch (\Throwable) {
            $connection->rollBack();
            $this->renderManage('タグの統合に失敗しました。もう一度お試しください。', null);
            return;
        }

        $count = count($sourceIds);
        $this->renderManage(null, "{$count}件のタグを「{$target['name']}」に統合しました。");
    }

    private function renderManage(?string $error, ?string $notice): void
    {
        View::render('tag_manage', [
            'title' => 'タグ管理',
            'notice' => $notice,
            'error' => $error,
            'tags' => Tag::findAllWithPostCount(),
        ]);
    }
}
