<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Cliente;

class AuthController extends BaseController
{
    /**
     * Registro de nuevo usuario
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
'telefono' => 'nullable|string|max:20',
            'rol' => 'in:administrador,vendedor,cliente'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'rol' => $request->rol ?? 'cliente',
        ]);
        Cliente::create([
    'user_id' => $user->id,
    'nombre_cliente' => $user->name,
    'email' => $user->email,
    'telefono' => $request->telefono ?? '000000000',
    'fecha_registro' => now(),
]);


        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->createdResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->rol,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Usuario registrado exitosamente');
    }

    /**
     * Login de usuario
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Credenciales incorrectas', 401);
        }

        // Eliminar tokens anteriores
        $user->tokens()->delete();

        // Crear nuevo token
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->rol,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login exitoso');
    }

    /**
     * Logout de usuario
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Sesión cerrada exitosamente');
    }

    /**
     * Obtener usuario autenticado
     */
    public function me(Request $request)
    {
        return $this->successResponse([
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'rol' => $request->user()->rol,
        ]);
    }
    /**
 * Obtener perfil completo del usuario autenticado (incluye datos de cliente)
 */
public function miPerfil(Request $request)
{
    $user = $request->user();
    $cliente = $user->cliente;

    if (!$cliente) {
        return $this->errorResponse('Usuario no tiene perfil de cliente', 404);
    }

    return $this->successResponse([
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'rol' => $user->rol,
        ],
        'cliente' => [
            'cliente_id' => $cliente->cliente_id,
            'nombre_cliente' => $cliente->nombre_cliente,
            'telefono' => $cliente->telefono,
            'email' => $cliente->email,
            'direccion' => $cliente->direccion,
            'fecha_registro' => $cliente->fecha_registro,
        ]
    ]);
}
}