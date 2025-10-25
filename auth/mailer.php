<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php'; // MAIL_FROM / MAIL_FROM_NAME

if (!function_exists('send_mail')) {
  /**
   * 日本語メール送信用: ISO-2022-JP(※拡張: MS)に変換して送信
   * - 件名: RFC 2047 (ISO-2022-JP)でエンコード
   * - 本文: ISO-2022-JP-MS に変換 + 7bit
   * - ヘッダ: From 名も MIME エンコード
   * - 行末: CRLF
   */
  function send_mail(string $to, string $subject, string $body, ?string $from = null, ?string $fromName = null): bool {
    $from     = $from     ?: MAIL_FROM;
    $fromName = $fromName ?: MAIL_FROM_NAME;

    // mbstring の既定
    if (function_exists('mb_language')) { @mb_language('Japanese'); }
    if (function_exists('mb_internal_encoding')) { @mb_internal_encoding('UTF-8'); }

    // 改行は CRLF に統一
    $normalizeLf = static function (string $s): string {
      $s = str_replace(["\r\n", "\r", "\n"], "\n", $s);
      return str_replace("\n", "\r\n", $s);
    };

    // 本文を JIS へ（MS 拡張で機種依存文字に配慮）
    $bodyJis = function_exists('mb_convert_encoding')
      ? mb_convert_encoding($body, 'ISO-2022-JP-MS', 'UTF-8')
      : $body; // フォールバック
    $bodyJis = $normalizeLf($bodyJis);

    // 件名・From 名を MIME ヘッダ用に JIS へ
    $encodedSubject = function_exists('mb_encode_mimeheader')
      ? mb_encode_mimeheader($subject, 'ISO-2022-JP-MS', 'B', "\r\n")
      : '=?ISO-2022-JP?B?' . base64_encode($subject) . '?=';

    $fromNameEnc = function_exists('mb_encode_mimeheader')
      ? mb_encode_mimeheader($fromName, 'ISO-2022-JP-MS', 'B', "\r\n")
      : '=?ISO-2022-JP?B?' . base64_encode($fromName) . '?=';

    // ヘッダ作成
    $headers = [];
$headers .= \"From: no-reply@fossil.dojin.com\r\n\";
$headers .= \"Content-Type: text/plain; charset=UTF-8\r\n\";
    $headers[] = 'From: ' . $fromNameEnc . " <{$from}>";
    $headers[] = 'Reply-To: ' . $from;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=ISO-2022-JP';
    $headers[] = 'Content-Transfer-Encoding: 7bit';
    $headerStr = implode("\r\n", $headers);

    // Envelope From（バウンス先）
    $params = "-f {$from}";

    // 送信
    $ok = false;
    if (function_exists('mb_send_mail')) {
      $ok = @mb_send_mail($to, $encodedSubject, $bodyJis, $headerStr, $params);
    } else {
      // フォールバック: mail()
      $ok = @mail($to, $encodedSubject, $bodyJis, $headerStr, $params);
    }

    if (!$ok) {
      error_log('[MAIL] send_mail failed: to=' . $to . ', subject=' . $subject);
    }
    return (bool)$ok;
  }
}
