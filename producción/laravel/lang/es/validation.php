<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'email' => 'El campo :attribute debe ser una dirección de correo válida.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'attributes' => [
        'email' => 'correo electrónico',
        'password' => 'contraseña',
        'password_confirmation' => 'confirmación de contraseña',
        'name' => 'nombres',
        'last_name' => 'apellidos',
        'phone' => 'teléfono',
        'roles' => 'perfiles',
        'roles.*' => 'perfil',
        'status' => 'estado',
        'tax_id' => 'identificación tributaria',
        'legal_name' => 'razón social',
        'address' => 'dirección',
        'country' => 'país',
        'administrative_division' => 'departamento o estado',
        'city' => 'ciudad o municipio',
        'permissions' => 'permisos',
        'permissions.*' => 'permiso',
    ],
];
