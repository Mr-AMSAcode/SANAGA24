<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('home') }}</loc>
        <changefreq>hourly</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>{{ route('posts.index') }}</loc>
        <changefreq>hourly</changefreq>
        <priority>0.8</priority>
    </url>
    @foreach ($sections as $section)
    <url>
        <loc>{{ route($section->value) }}</loc>
        <changefreq>hourly</changefreq>
        <priority>0.8</priority>
    </url>
    @endforeach
    @foreach ($posts as $post)
    <url>
        <loc>{{ route('posts.show', $post) }}</loc>
        <lastmod>{{ $post->updated_at->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
    @endforeach
</urlset>
