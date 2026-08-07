<x-layouts::app :title="'Unsubscribed — Sanaga24'">
    <div class="min-h-[60vh] flex items-center justify-center px-4">
        <div class="text-center max-w-sm">
            <h1 class="text-2xl font-black text-stone-900 mb-2">You're unsubscribed</h1>
            <p class="text-stone-500 text-sm mb-6">
                You won't receive any more newsletter emails from us. You can resubscribe any time from the homepage.
            </p>
            <a wire:navigate href="{{ route('home') }}"
               class="inline-flex px-5 py-2.5 text-sm font-bold text-stone-900 bg-amber-400
                      hover:bg-amber-500 rounded-lg transition-colors">
                Back to Sanaga24
            </a>
        </div>
    </div>
</x-layouts::app>
