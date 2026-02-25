<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar - Kipu</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4">

    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl overflow-hidden">
        <div class="p-8 md:p-10">
            <div class="text-center mb-10">
                <h1 class="text-3xl font-black text-gray-800">KIPU</h1>
                <p class="text-gray-500 mt-2 text-sm">Ingresa a tu cuenta de restaurante</p>
            </div>

            <form action="{{ route('login') }}" method="POST" class="space-y-6">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Correo Electrónico</label>
                    <input type="email" name="email" required 
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#ce6439] focus:ring-2 focus:ring-[#ce6439]/20 outline-none transition-all"
                        placeholder="tu@correo.com">
                </div>

                <div>
                    <div class="flex justify-between mb-2">
                        <label class="text-xs font-bold text-gray-700 uppercase tracking-wider">Contraseña</label>
                        <a href="#" class="text-xs font-bold text-[#ce6439] hover:underline">¿Olvidaste tu contraseña?</a>
                    </div>
                    <input type="password" name="password" required 
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#ce6439] focus:ring-2 focus:ring-[#ce6439]/20 outline-none transition-all"
                        placeholder="••••••••">
                </div>

                <div class="flex items-center">
                    <input type="checkbox" id="remember" class="w-4 h-4 text-[#ce6439] rounded border-gray-300">
                    <label for="remember" class="ml-2 text-sm text-gray-600">Mantener sesión iniciada</label>
                </div>

                <button type="submit" 
                    class="w-full bg-[#ce6439] hover:bg-[#0b4d2e] text-white font-bold py-3.5 rounded-xl shadow-lg shadow-[#ce6439]/20 transition-all transform active:scale-95">
                    Iniciar Sesión
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                <p class="text-sm text-gray-500">¿No tienes cuenta? 
                    <a href="#" class="font-bold text-[#ce6439] hover:underline">Contáctanos</a>
                </p>
            </div>
        </div>
    </div>

</body>
</html>