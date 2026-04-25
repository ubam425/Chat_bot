<?php
declare(strict_types=1);

final class Chatbot
{
    public static function reply(string $message): string
    {
        $text = mb_strtolower(trim($message));

        if ($text === '') {
            return 'Estoy aqui para ayudarte. Escribe un mensaje y te respondo de forma sencilla.';
        }

        if (preg_match('/\b(hola|buenas|hey|que tal|saludos)\b/u', $text) === 1) {
            return 'Hola. Soy ChatUbam Bot. Si quieres, escribe "menu" para ver lo que puedo responder.';
        }

        if (preg_match('/\b(menu|opciones|comandos|ayuda|help|soporte)\b/u', $text) === 1) {
            return "Puedo responder estas opciones rapidas: hora, fecha, quien eres, contacto, tips, gracias, adios, como estas.";
        }

        if (preg_match('/\b(como estas|como te va|todo bien)\b/u', $text) === 1) {
            return 'Estoy muy bien y listo para ayudarte con tu chat.';
        }

        if (preg_match('/\b(gracias|muchas gracias|te agradezco)\b/u', $text) === 1) {
            return 'Con gusto. Estoy para apoyarte.';
        }

        if (preg_match('/\b(adios|hasta luego|nos vemos|bye)\b/u', $text) === 1) {
            return 'Hasta luego. Cuando quieras, aqui estare.';
        }

        if (preg_match('/\b(hora|horario)\b/u', $text) === 1) {
            return 'En este momento son las ' . date('H:i') . ' (hora local).';
        }

        if (preg_match('/\b(fecha|dia)\b/u', $text) === 1) {
            $days = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
            $months = [
                1 => 'enero',
                2 => 'febrero',
                3 => 'marzo',
                4 => 'abril',
                5 => 'mayo',
                6 => 'junio',
                7 => 'julio',
                8 => 'agosto',
                9 => 'septiembre',
                10 => 'octubre',
                11 => 'noviembre',
                12 => 'diciembre',
            ];

            $today = new DateTimeImmutable('now');
            $dayName = $days[(int) $today->format('w')] ?? '';
            $monthName = $months[(int) $today->format('n')] ?? '';

            return 'Hoy es ' . $dayName . ' ' . $today->format('d') . ' de ' . $monthName . ' de ' . $today->format('Y') . '.';
        }

        if (preg_match('/\b(quien eres|tu nombre|eres bot|bot)\b/u', $text) === 1) {
            return 'Soy ChatUbam Bot, un asistente basico integrado en tu chat.';
        }

        if (preg_match('/\b(contacto|correo|telefono)\b/u', $text) === 1) {
            return 'Para soporte puedes escribir al correo administrador configurado en el sistema.';
        }

        if (preg_match('/\b(tips|consejos|recomendaciones)\b/u', $text) === 1) {
            return 'Tip rapido: usa el buscador para encontrar chats y el boton + para adjuntar archivos, fotos o videos.';
        }

        if (preg_match('/\b(si|no)\b/u', $text) === 1) {
            return 'Perfecto. Si necesitas algo puntual, escribe "menu" para ver opciones rapidas.';
        }

        $fallbacks = [
            'Te lei. Si quieres respuestas rapidas, escribe "menu".',
            'Entendi tu mensaje. Puedo ayudarte con opciones basicas del chat.',
            'Sigo aqui. Puedes preguntarme hora, fecha o escribir "ayuda".',
        ];

        return $fallbacks[array_rand($fallbacks)];
    }
}
