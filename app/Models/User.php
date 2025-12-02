<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'rol',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Verificar si es administrador
     */
    public function esAdministrador()
    {
        return $this->rol === 'administrador';
    }

    /**
     * Verificar si es vendedor
     */
    public function esVendedor()
    {
        return $this->rol === 'vendedor';
    }

    /**
     * Verificar si es cliente
     */
    public function esCliente()
    {
        return $this->rol === 'cliente';
    }

    /**
     * Scope: Solo administradores
     */
    public function scopeAdministradores($query)
    {
        return $query->where('rol', 'administrador');
    }

    /**
     * Scope: Solo vendedores
     */
    public function scopeVendedores($query)
    {
        return $query->where('rol', 'vendedor');
    }
    /**
 * Relación: Un usuario tiene un cliente
 */
public function cliente()
{
    return $this->hasOne(Cliente::class, 'user_id', 'id');
}
}
