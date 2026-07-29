<x-layouts::app.header :title="$title ?? null">
    <main>
        {{ $slot }}
    </main>
</x-layouts::app.header>
