<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    @include('partials.nav')

    <main>
        {{ $slot }}
    </main>

    @include('partials.footer')

    @fluxScripts
</body>

</html>
