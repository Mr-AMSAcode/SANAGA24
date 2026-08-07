<?php

namespace App\Support;

/**
 * Recognizes a pasted YouTube/Vimeo URL and normalizes it into an
 * embeddable iframe URL. Deliberately narrow — only the two providers
 * editors actually asked for, not a generic oEmbed resolver.
 */
class VideoEmbedUrl
{
    /**
     * @return array{provider: string, url: string}|null null when the URL
     *         isn't a recognized YouTube/Vimeo link.
     */
    public static function resolve(string $rawUrl): ?array
    {
        $rawUrl = trim($rawUrl);

        if ($id = self::youtubeId($rawUrl)) {
            return ['provider' => 'youtube', 'url' => "https://www.youtube.com/embed/{$id}"];
        }

        if ($id = self::vimeoId($rawUrl)) {
            return ['provider' => 'vimeo', 'url' => "https://player.vimeo.com/video/{$id}"];
        }

        return null;
    }

    private static function youtubeId(string $url): ?string
    {
        // youtu.be/ID · youtube.com/watch?v=ID · youtube.com/embed/ID · youtube.com/shorts/ID
        if (preg_match('#youtu\.be/([A-Za-z0-9_-]{6,})#', $url, $m)) {
            return $m[1];
        }

        if (preg_match('#youtube\.com/(?:watch\?v=|embed/|shorts/)([A-Za-z0-9_-]{6,})#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    private static function vimeoId(string $url): ?string
    {
        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
