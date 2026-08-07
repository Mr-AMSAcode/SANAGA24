<x-mail::message>
# This week on Sanaga24

Here's what you might have missed:

@foreach ($posts as $post)
## {{ $post->title }}

{{ \Illuminate\Support\Str::limit(strip_tags($post->content), 200) }}

<x-mail::button :url="route('posts.show', $post)">
Read more
</x-mail::button>

---
@endforeach

Thanks for reading,<br>
{{ config('app.name') }}

<x-mail::subcopy>
Don't want these emails? [Unsubscribe here]({{ $unsubscribeUrl }}).
</x-mail::subcopy>
</x-mail::message>
