<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Surfacewatch') }} – find the leaks attackers scan for</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
        <div class="mx-auto flex min-h-screen max-w-5xl flex-col px-6">
            <header class="flex items-center justify-between py-6">
                <div class="flex items-center gap-2 font-semibold">
                    <span class="flex size-8 items-center justify-center rounded-md bg-white text-zinc-950">
                        <x-app-logo-icon class="size-5" />
                    </span>
                    {{ config('app.name', 'Surfacewatch') }}
                </div>
                <nav class="flex items-center gap-3 text-sm">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-md bg-white px-4 py-2 font-medium text-zinc-950 hover:bg-zinc-200">{{ __('Dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="px-3 py-2 text-zinc-300 hover:text-white">{{ __('Log in') }}</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="rounded-md bg-white px-4 py-2 font-medium text-zinc-950 hover:bg-zinc-200">{{ __('Get started') }}</a>
                        @endif
                    @endauth
                </nav>
            </header>

            <main class="flex flex-1 flex-col justify-center py-16">
                <p class="text-sm font-medium uppercase tracking-widest text-red-400">{{ __('Security monitoring for Laravel teams') }}</p>
                <h1 class="mt-4 max-w-3xl text-4xl font-semibold leading-tight sm:text-5xl">
                    {{ __('Find the leaks attackers scan for — before they do.') }}
                </h1>
                <p class="mt-6 max-w-2xl text-lg text-zinc-400">
                    {{ __('Botnets like Androxgh0st crawl the internet for exposed .env files, debug pages and open admin dashboards. Surfacewatch checks every site you look after for exactly those mistakes, watches your Composer packages for new vulnerabilities, and tells you in plain English how to fix each problem.') }}
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ Route::has('register') ? route('register') : route('login') }}" class="rounded-md bg-red-500 px-5 py-3 font-medium text-white hover:bg-red-400">{{ __('Start monitoring') }}</a>
                    <a href="{{ route('bot') }}" class="rounded-md border border-zinc-700 px-5 py-3 font-medium text-zinc-200 hover:border-zinc-500">{{ __('How scanning works') }}</a>
                </div>

                <div class="mt-16 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-zinc-800 p-5">
                        <div class="text-sm font-semibold text-white">{{ __('18 targeted checks') }}</div>
                        <p class="mt-2 text-sm text-zinc-400">{{ __('Exposed .env and .git, debug mode, Telescope, Horizon and log viewers, document root mistakes, TLS, headers, cookies, SPF and DMARC.') }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-800 p-5">
                        <div class="text-sm font-semibold text-white">{{ __('Fleet-wide advisories') }}</div>
                        <p class="mt-2 text-sm text-zinc-400">{{ __('A tiny agent reports installed packages. When a new CVE lands, see every client app it affects in one list.') }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-800 p-5">
                        <div class="text-sm font-semibold text-white">{{ __('Safe by design') }}</div>
                        <p class="mt-2 text-sm text-zinc-400">{{ __('Ownership verification before any path probe, SSRF protection, and secret values are never stored.') }}</p>
                    </div>
                </div>
            </main>

            <footer class="py-6 text-sm text-zinc-500">{{ __('Built by Owais Mastoor · Laravel 13, Livewire and Flux.') }}</footer>
        </div>
    </body>
</html>
