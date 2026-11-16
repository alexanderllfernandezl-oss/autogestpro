
<?php

require_once __DIR__ . '/../models/Usuario.php';

class AuthController
{
    public function login(): void
    {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';

            if (
                !is_string($token) ||
                !isset($_SESSION['csrf_token']) ||
                !is_string($_SESSION['csrf_token']) ||
                !hash_equals($_SESSION['csrf_token'], $token)
            ) {
                http_response_code(403);
                $error = 'La solicitud no es válida. Recarga la página.';
            } else {
                $nombreUsuario = trim($_POST['usuario'] ?? '');
                $password = $_POST['password'] ?? '';

                if (!is_string($password)) {
                    $password = '';
                }

                if ($nombreUsuario === '' || $password === '') {
                    $error = 'Introduce tu usuario y contraseña.';
                } else {
                    $modelo = new Usuario();
                    $usuario = $modelo->buscarPorUsuario($nombreUsuario);

                    if (
                        $usuario &&
                        (int) $usuario['activo'] === 1 &&
                        password_verify(
                            $password,
                            $usuario['password_hash']
                        )
                    ) {
                        session_regenerate_id(true);

                        $_SESSION['usuario_id'] = (int) $usuario['id'];
                        $_SESSION['nombre'] = $usuario['nombre'];
                        $_SESSION['rol'] = $usuario['rol'];

                        if (isset($_POST['recordarme']) && $_POST['recordarme'] === '1') {
                            $duracion = 30 * 24 * 60 * 60;
                            $params = session_get_cookie_params();
                            setcookie(
                                session_name(),
                                session_id(),
                                [
                                    'expires' => time() + $duracion,
                                    'path' => $params['path'],
                                    'domain' => $params['domain'],
                                    'secure' => $params['secure'],
                                    'httponly' => $params['httponly'],
                                    'samesite' => $params['samesite'] ?? 'Lax'
                                ]
                            );
                        }

                        header('Location: ' . (($_SESSION['rol'] ?? '') === 'mecanico' ? '?page=ordenes' : '?page=home'));
                        exit;
                    }

                    $error = 'Usuario o contraseña incorrectos.';
                }
            }
        }

        require __DIR__ . '/../views/auth/login.php';
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido.';
            return;
        }

        $token = $_POST['csrf_token'] ?? '';

        if (
            !is_string($token) ||
            !isset($_SESSION['csrf_token']) ||
            !is_string($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $token)
        ) {
            http_response_code(403);
            echo 'Solicitud no válida.';
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => 'Lax'
                ]
            );
        }

        session_destroy();

        header('Location: ?page=login');
        exit;
    }
}
