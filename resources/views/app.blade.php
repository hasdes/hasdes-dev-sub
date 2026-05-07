<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta content="width=device-width, initial-scale=1.0" name="viewport">

        <title>HASDES</title>

        <!-- Favicon -->
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('/favicon.png') }}">
        <!-- FontAwesome -->
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.0.0/css/all.css"
        integrity="sha384-3B6NwesSXE7YJlcLI9RpRqGf2p/EgVH8BgoKTaUrmKNDkHPStTQ3EyoYjCGXaOTS" crossorigin="anonymous"/>

        <!-- Vite -->
        @vite(['resources/css/bootstrap.min.css', 'resources/css/style.css','resources/js/app.js'])
      
        

        <!-- jQuery Tablesorter -->
        <script src="{{ asset('/js/main.js') }}"></script>
        <script src="{{ asset('/js/menu.js') }}"></script>
    </head>
    <body>
        <div id="app" class="content"></div>
        <!-- <footer id="footer" class="footer">
            <ul>
            <li><a href="../use.html">利用規約</a></li>
            <li><a href="../privacy.html">プライバシーポリシー</a></li>
            </ul>
            <div class="copyright"><a href="../index.html"> &copy; 2024 HASDES</a></div>
        </footer> -->
    </body>
</html>
