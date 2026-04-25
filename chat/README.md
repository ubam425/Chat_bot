# ChatUbam

Chat web en **PHP + MySQL + HTML/CSS/JS** con interfaz inspirada en WhatsApp.

## Funcionalidades

- Registro de usuarios con datos:
  - nombre(s)
  - apellido paterno
  - apellido materno
  - telefono
  - correo
- Contrasena generada automaticamente y enviada por correo SMTP.
- Inicio de sesion con correo + contrasena.
- Recuperacion de contrasena por correo.
- Boton de **auto-login de usuario de prueba**.
- Chat 1 a 1 entre todos los usuarios registrados.
- Mensajeria en tiempo real por polling (actualizacion automatica).
- Envio de archivos, imagenes y videos.
- Chatbot basico integrado (`ChatUbam Bot`).
- Login biometrico con **WebAuthn/Passkeys** (huella o rostro del dispositivo).

## Requisitos

- PHP 8.1+
- Extension `pdo_mysql`
- MySQL/MariaDB 10+
- Extension `openssl`

## Configuracion

1. Copia variables de entorno:

```bash
cp .env.example .env
```

2. Crea la base de datos en MySQL:

```sql
CREATE DATABASE chatubam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. Ajusta `.env` (credenciales de BD, URL y SMTP).

4. Ejecuta instalacion:

- Abre `http://localhost/chat/setup.php`
- o desde servidor local apuntando a este proyecto

Esto crea tablas, usuario bot y usuario de prueba.

## Credenciales de prueba

- Correo: `sicawol744@donumart.com`
- Password: `Su@rFBYWLpc5`

Tambien puedes usar el boton: **Entrar con usuario de prueba**.

## SMTP actual

El proyecto ya viene preconfigurado en `.env.example` con:

- Host: `smtp.hostinger.com`
- Puerto: `465`
- Seguridad: `ssl`
- Usuario: `admin@chat.portafolioestudiantil.com`

## Ejecutar local

```bash
php -S localhost:8000
```

Luego abre:

- `http://localhost:8000/setup.php` (primera vez)
- `http://localhost:8000/index.php`

## Nota biometria

La biometria usa WebAuthn (passkeys). El navegador debe ser compatible (Chrome/Edge/Safari modernos) y el dispositivo debe tener huella/rostro habilitado.

## Cambiar color facilmente

Edita variables CSS al inicio de:

- `assets/css/style.css`

Variables principales:

- `--brand`
- `--brand-dark`
- `--brand-soft`
- `--accent`
