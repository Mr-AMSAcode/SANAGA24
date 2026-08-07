<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

<rss version="2.0">
    <channel>
        <title>Sanaga24</title>
        <link>{{ route('home') }}</link>
        <description>Actualités — politique, sport, culture, science, opinion et monde.</description>
        <language>en</language>
        <lastBuildDate>{{ now()->toRfc2822String() }}</lastBuildDate>
        <atom:link xmlns:atom="http://www.w3.org/2005/Atom" href="{{ route('feed') }}" rel="self" type="application/rss+xml"/>
        @foreach ($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ route('posts.show', $post) }}</link>
            <guid isPermaLink="true">{{ route('posts.show', $post) }}</guid>
            <pubDate>{{ $post->created_at->toRfc2822String() }}</pubDate>
            @if ($post->editor)
            <dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">{{ $post->editor->name }}</dc:creator>
            @endif
            <category>{{ $post->section->label() }}</category>
            <description>{{ \Illuminate\Support\Str::limit(strip_tags($post->content), 300) }}</description>
        </item>
        @endforeach
    </channel>
</rss>
