<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="alternate" type="application/rss+xml" title="Sanaga24 — RSS" href="{{ route('feed') }}">

<link rel="icon" href="{{ asset('logo.jpeg') }}" sizes="any">
<link rel="apple-touch-icon" href="{{ asset('logo.jpeg') }}">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
