<?php

namespace App\Http\Controllers\Api;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClienteController extends BaseController
{
    /**
     * Listar todos los clientes
     */
    public function index(Request $request)
    {
        $query = Cliente::query();

        if ($request->has('buscar')) {
            $buscar = $request->buscar;
            $query->where(function($q) use ($buscar) {
                $q->where('nombre_clie', 'like', "%{$buscar}%")
                  ->orWhere('telefono', 'like', "%{$buscar}%")
                  ->orWhere('email', 'like', "%{$buscar}%");
            });
        }

        $orderBy = $request->get('order_by', 'fecha_registro');
        $orderDir = $request->get('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);

        $perPage = $request->get('per_page', 15);
        $clientes = $query->paginate($perPage);

        return $this->successResponse($clientes);
    }

    /**
     * Obtener un cliente específico
     */
    public function show($id)
    {
        $cliente = Cliente::with(['ventas', 'pedidosPersonalizados'])->find($id);

        if (!$cliente) {
            return $this->notFoundResponse('Cliente no encontrado');
        }

        $totalCompras = $cliente->ventas()
            ->where('estado_venta', 'Completada')
            ->count();
        
        $totalGastado = $cliente->ventas()
            ->where('estado_venta', 'Completada')
            ->sum('total_venta');

        $cliente->estadisticas = [
            'total_compras' => $totalCompras,
            'total_gastado' => round($totalGastado, 2),
        ];

        return $this->successResponse($cliente);
    }

    /**
     * Crear nuevo cliente
     */
   /**
     * Crear nuevo cliente
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'dni' => 'nullable|string|max:20',
            'telefono' => 'required|string|max:9',
            'email' => 'nullable|email|max:100',
            'direccion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // ⭐ USAR NOMBRES CORRECTOS DE COLUMNAS
            $cliente = Cliente::create([
                'nombre_cliente' => $request->nombre,   // ✅ CORRECTO
                'dni' => $request->dni,                  // ⭐ NUEVO
                'telefono' => $request->telefono,
                'email' => $request->email,
                'direccion' => $request->direccion,
                'fecha_registro' => now(),
            ]);

            \Log::info('✅ Cliente creado:', [
                'id' => $cliente->cliente_id,
                'nombre' => $cliente->nombre_cliente
            ]);

            return response()->json([
                'success' => true,
                'data' => $cliente,
                'message' => 'Cliente registrado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            \Log::error('❌ Error al crear cliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Actualizar cliente
     */
public function update(Request $request, $id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return $this->notFoundResponse('Cliente no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'string|max:100',
            'dni' => 'nullable|string|max:20',
            'telefono' => 'string|max:9',
            'email' => 'nullable|email|max:50|unique:clientes,email,' . $id . ',cliente_id',
            'direccion' => 'nullable|string',
            'contacto' => 'nullable|string|max:50',
            'preferencias' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        // ⭐ MAPEAR A NOMBRES CORRECTOS DE COLUMNAS
        $datosActualizar = [];
        
        if ($request->has('nombre')) {
            $datosActualizar['nombre_cliente'] = $request->nombre;
        }
        if ($request->has('dni')) {
            $datosActualizar['dni'] = $request->dni;
        }
        if ($request->has('telefono')) {
            $datosActualizar['telefono'] = $request->telefono;
        }
        if ($request->has('email')) {
            $datosActualizar['email'] = $request->email;
        }
        if ($request->has('direccion')) {
            $datosActualizar['direccion'] = $request->direccion;
        }
        if ($request->has('contacto')) {
            $datosActualizar['contacto_cliente'] = $request->contacto;
        }
        if ($request->has('preferencias')) {
            $datosActualizar['preferencias_cliente'] = $request->preferencias;
        }

        $cliente->update($datosActualizar);

        return $this->successResponse($cliente, 'Cliente actualizado exitosamente');
    }
    /**
     * Eliminar cliente
     */
    public function destroy($id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return $this->notFoundResponse('Cliente no encontrado');
        }

        if ($cliente->ventas()->count() > 0) {
            return $this->errorResponse(
                'No se puede eliminar un cliente con ventas asociadas',
                400
            );
        }

        $cliente->delete();
        return $this->successResponse(null, 'Cliente eliminado exitosamente');
    }

    /**
     * Buscar cliente por teléfono
     */
    public function buscarPorTelefono($telefono)
    {
        try {
            $cliente = Cliente::where('telefono', $telefono)->first();

            if ($cliente) {
                return response()->json([
                    'success' => true,
                    'data' => $cliente,
                    'message' => 'Cliente encontrado'
                ]);
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Cliente no encontrado'
            ], 404);

        } catch (\Exception $e) {
            \Log::error('❌ Error al buscar cliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al buscar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }
/** 
     * Buscar cliente por nombre, DNI o teléfono (búsqueda universal)
     */
  /**
     * Buscar cliente por nombre, DNI o teléfono (búsqueda universal)
     */
    public function buscar(Request $request)
    {
        try {
            $busqueda = $request->get('q');

            if (!$busqueda) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe proporcionar un término de búsqueda',
                    'data' => []
                ], 400);
            }

            // Buscar por nombre, DNI o teléfono
            $clientes = Cliente::where(function($query) use ($busqueda) {
                $query->where('nombre_cliente', 'LIKE', "%{$busqueda}%")
                      ->orWhere('dni', 'LIKE', "%{$busqueda}%")
                      ->orWhere('telefono', 'LIKE', "%{$busqueda}%");
            })
            ->limit(10)
            ->get();

            if ($clientes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron clientes',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Clientes encontrados',
                'data' => $clientes
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Error al buscar cliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al buscar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Obtener clientes frecuentes
     */
    public function clientesFrecuentes()
    {
        $clientes = Cliente::withCount(['ventas' => function($query) {
            $query->where('estado_venta', 'Completada');
        }])
        ->with(['ventas' => function($query) {
            $query->where('estado_venta', 'Completada')
                  ->select('cliente_id', 'total_venta');
        }])
        ->having('ventas_count', '>=', 3)
        ->orderBy('ventas_count', 'desc')
        ->limit(10)
        ->get();

        $clientesConTotal = $clientes->map(function($cliente) {
            $totalGastado = $cliente->ventas->sum('total_venta');
            return [
                'cliente_id' => $cliente->cliente_id,
                'nombre_clie' => $cliente->nombre_clie,
                'telefono' => $cliente->telefono,
                'email' => $cliente->email,
                'total_compras' => $cliente->ventas_count,
                'total_gastado' => round($totalGastado, 2),
            ];
        });

        return $this->successResponse($clientesConTotal);
    }

    /**
     * Actualizar preferencias del cliente
     */
    public function actualizarPreferencias(Request $request, $id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return $this->notFoundResponse('Cliente no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'preferencias_clie' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $cliente->preferencias_clie = $request->preferencias_clie;
        $cliente->save();

        return $this->successResponse($cliente, 'Preferencias actualizadas exitosamente');
    }
}