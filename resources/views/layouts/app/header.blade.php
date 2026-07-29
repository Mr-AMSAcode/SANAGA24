@php use App\Enums\PostSection; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:header
        container
        sticky
        class="border-b border-zinc-700 dark:border-zinc-700 bg-white dark:bg-zinc-900"
    >
        <div class="relative flex items-center justify-between w-full h-20 lg:h-16">

            <div class="flex items-center lg:hidden">
                <flux:sidebar.toggle icon="bars-2" inset="left" />
            </div>

            <div class="absolute left-1/2 -translate-x-1/2 lg:static lg:translate-x-0 hover:cursor-pointer">
                <x-app-logo-icon
                    href="{{ route('home') }}"
                    wire:navigate
                    class="h-20 lg:h-10 w-auto"
                />
            </div>

            <flux:navbar class="hidden lg:flex items-center gap-6 h-full ml-6">
                @foreach(PostSection::cases() as $category)
                    <flux:navbar.item
                        :href="route(strtolower($category->name))"
                        :current="request()->routeIs(strtolower($category->name))"
                        wire:navigate
                        class="h-full flex items-center pb-1 text-sm font-medium
                       text-zinc-600 dark:text-zinc-300
                       hover:text-black hover:bg-blue-100 dark:hover:text-white
                       border-b-2 border-transparent
                       data-[current=true]:border-black dark:data-[current=true]:border-white"
                    >
                        {{ ucfirst(strtolower($category->name)) }}
                    </flux:navbar.item>
                @endforeach
            </flux:navbar>

        </div>
    </flux:header>

    <!-- Mobile Menu -->
    <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 w-64">
        <flux:sidebar.header>
            <x-app-logo-icon class="h-20 w-auto mr-2" :sidebar="true" href="{{ route('home') }}" wire:navigate />
            <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Sections')">
                @foreach(PostSection::cases() as $category)
                    <flux:sidebar.item
                        :href="route(strtolower($category->name))"
                        :current="request()->routeIs(strtolower($category->name))"
                        wire:navigate
                    >
                        {{ ucfirst(strtolower($category->name)) }}
                    </flux:sidebar.item>
                @endforeach
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:spacer />

    </flux:sidebar>

    {{ $slot }}

    @fluxScripts
</body>

</html>
