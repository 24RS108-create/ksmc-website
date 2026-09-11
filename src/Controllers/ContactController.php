<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Models\Inquiry;

/**
 * お問い合わせ（FR-22）。フォーム送信は誰でも可能、内容の閲覧・対応管理は管理者のみ。
 */
final class ContactController
{
    private const MAX_BODY_LENGTH = 2000;

    public function showForm(): void
    {
        View::render('contact', [
            'title' => 'お問い合わせ',
            'errors' => [],
            'notice' => null,
            'formEmail' => '',
            'formBody' => '',
        ]);
    }

    public function submit(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $honeypot = trim((string) ($_POST['website'] ?? ''));
        $errors = [];

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $errors[] = '不正なリクエストです。もう一度お試しください。';
        }

        // ボットが自動入力しがちな隠しフィールドに値があれば、正常応答を装って破棄する。
        if ($honeypot !== '') {
            View::render('contact', [
                'title' => 'お問い合わせ',
                'errors' => [],
                'notice' => 'お問い合わせを受け付けました。',
                'formEmail' => '',
                'formBody' => '',
            ]);
            return;
        }

        if ($body === '') {
            $errors[] = '本文を入力してください。';
        } elseif (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            $errors[] = '本文は' . self::MAX_BODY_LENGTH . '文字以内で入力してください。';
        }

        if ($email !== '') {
            if (mb_strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'メールアドレスの形式が正しくありません。';
            }
        }

        if (!empty($errors)) {
            View::render('contact', [
                'title' => 'お問い合わせ',
                'errors' => $errors,
                'notice' => null,
                'formEmail' => $email,
                'formBody' => $body,
            ]);
            return;
        }

        Inquiry::create($email, $body);

        View::render('contact', [
            'title' => 'お問い合わせ',
            'errors' => [],
            'notice' => 'お問い合わせを受け付けました。返信が必要な場合は、いただいたメールアドレス宛に担当者からご連絡します。',
            'formEmail' => '',
            'formBody' => '',
        ]);
    }

    public function showManage(): void
    {
        Auth::requireRole('admin');

        View::render('contact_manage', [
            'title' => 'お問い合わせ管理',
            'notice' => null,
            'inquiries' => Inquiry::findAll(),
        ]);
    }

    public function updateStatus(): void
    {
        Auth::requireRole('admin');

        $id = (int) ($_POST['id'] ?? 0);

        if (!Csrf::verify($_POST['csrf_token'] ?? null) || $id <= 0 || !Inquiry::exists($id)) {
            header('Location: /contact_manage.php');
            exit;
        }

        Inquiry::toggleHandled($id);

        View::render('contact_manage', [
            'title' => 'お問い合わせ管理',
            'notice' => '対応状態を更新しました。',
            'inquiries' => Inquiry::findAll(),
        ]);
    }
}
