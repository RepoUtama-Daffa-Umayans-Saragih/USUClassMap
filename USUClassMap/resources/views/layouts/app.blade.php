<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'USUStarterMap' }} - Peta Ruang Kelas Kampus USU</title>
    @stack('meta')
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body 
    class="bg-[#F3F4F6] text-gray-900 font-sans antialiased" 
    x-data="{ mobileMenu: false }"
>
    <x-navbar.public />

    <!-- Main Content Area -->
    <main class="pt-16 min-h-[calc(100vh-4rem)]">
        {{ $slot }}
    </main>

    <!-- ========================================================================= -->
    <!-- TANYA UCUP AI CHATBOT WIDGET (PHASE 3)                                 -->
    <!-- ========================================================================= -->
    @if(config('copilot.enabled', env('COPILOT_ENABLED', true)))
        @vite(['resources/js/copilot.js'])
        @include('components.chatbot-widget')
    @endif

    @stack('scripts')
</body>
</html>
