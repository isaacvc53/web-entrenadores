<?php

/*
 * Mensajes de error de validación en español.
 * ":attribute" se sustituye por el nombre del campo (ver "attributes" al final).
 */

return [

    'accepted' => 'Debes aceptar :attribute.',
    'active_url' => ':Attribute no es una dirección web válida.',
    'after' => ':Attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => ':Attribute debe ser una fecha igual o posterior a :date.',
    'alpha' => ':Attribute solo puede contener letras.',
    'alpha_dash' => ':Attribute solo puede contener letras, números, guiones y guiones bajos.',
    'alpha_num' => ':Attribute solo puede contener letras y números.',
    'array' => ':Attribute debe ser una lista.',
    'before' => ':Attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => ':Attribute debe ser una fecha igual o anterior a :date.',
    'between' => [
        'array' => ':Attribute debe tener entre :min y :max elementos.',
        'file' => ':Attribute debe ocupar entre :min y :max KB.',
        'numeric' => ':Attribute debe estar entre :min y :max.',
        'string' => ':Attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => ':Attribute debe ser sí o no.',
    'confirmed' => 'Las contraseñas no coinciden.',
    'current_password' => 'La contraseña actual no es correcta.',
    'date' => ':Attribute no es una fecha válida.',
    'different' => ':Attribute no puede ser igual que :other.',
    'distinct' => ':Attribute tiene un valor repetido.',
    'email' => ':Attribute debe ser un email válido.',
    'exists' => ':Attribute no existe.',
    'file' => ':Attribute debe ser un archivo.',
    'filled' => ':Attribute no puede estar vacío.',
    'image' => ':Attribute debe ser una imagen.',
    'in' => ':Attribute no es válido.',
    'integer' => ':Attribute debe ser un número entero.',
    'lowercase' => ':Attribute debe estar en minúsculas.',
    'max' => [
        'array' => ':Attribute no puede tener más de :max elementos.',
        'file' => ':Attribute no puede ocupar más de :max KB.',
        'numeric' => ':Attribute no puede ser mayor que :max.',
        'string' => ':Attribute no puede tener más de :max caracteres.',
    ],
    'mimes' => ':Attribute debe ser de uno de estos tipos: :values.',
    'mimetypes' => ':Attribute debe ser de uno de estos tipos: :values.',
    'min' => [
        'array' => ':Attribute debe tener al menos :min elementos.',
        'file' => ':Attribute debe ocupar al menos :min KB.',
        'numeric' => ':Attribute debe ser como mínimo :min.',
        'string' => ':Attribute debe tener al menos :min caracteres.',
    ],
    'not_in' => ':Attribute no es válido.',
    'numeric' => ':Attribute debe ser un número.',
    'password' => [
        'letters' => ':Attribute debe tener al menos una letra.',
        'mixed' => ':Attribute debe tener al menos una mayúscula y una minúscula.',
        'numbers' => ':Attribute debe tener al menos un número.',
        'symbols' => ':Attribute debe tener al menos un símbolo.',
        'uncompromised' => 'Esta contraseña ha aparecido en filtraciones de datos. Elige otra.',
    ],
    'present' => 'Falta :attribute.',
    'prohibits' => 'Si envías :attribute no puedes enviar también :other.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => 'Falta :attribute.',
    'required_if' => 'Falta :attribute.',
    'required_with' => 'Falta :attribute.',
    'required_without' => 'Falta :attribute.',
    'same' => ':Attribute y :other deben coincidir.',
    'size' => [
        'array' => ':Attribute debe tener :size elementos.',
        'file' => ':Attribute debe ocupar :size KB.',
        'numeric' => ':Attribute debe ser :size.',
        'string' => ':Attribute debe tener :size caracteres.',
    ],
    'string' => ':Attribute debe ser un texto.',
    'unique' => 'Ya existe :attribute con ese valor.',
    'uploaded' => 'No se ha podido subir :attribute.',
    'url' => ':Attribute debe ser una dirección web válida.',

    /*
     * Mensajes para un campo y una regla concretos.
     */
    'custom' => [
        'email' => [
            'unique' => 'Ya hay una cuenta con ese email.',
        ],
        'archivo' => [
            'max' => 'El archivo no puede ocupar más de 10 MB.',
            'mimes' => 'Solo se pueden subir imágenes (JPG, PNG o WebP) y PDF.',
        ],
        'password' => [
            'different' => 'La contraseña nueva debe ser distinta de la actual.',
        ],
        'etiquetas' => [
            'max' => 'Una publicación puede tener como máximo :max etiquetas.',
        ],
        'etiquetas.*' => [
            'max' => 'Cada etiqueta puede tener como máximo :max caracteres.',
            'distinct' => 'Has puesto dos veces la misma etiqueta.',
        ],
        'foto' => [
            'max' => 'La foto no puede ocupar más de 10 MB.',
            'mimes' => 'La foto debe ser JPG, PNG o WebP.',
        ],
    ],

    /*
     * Nombre con el que sale cada campo en los mensajes.
     */
    'attributes' => [
        'activo' => 'el estado',
        'archivo' => 'el archivo',
        'category_id' => 'la categoría',
        'contenido' => 'el contenido',
        'desde' => 'la temporada de origen',
        'email' => 'el email',
        'etiquetas' => 'las etiquetas',
        'etiquetas.*' => 'la etiqueta',
        'fija' => 'la lista fija',
        'foto' => 'la foto',
        'hasta' => 'la temporada nueva',
        'ids' => 'la lista',
        'moderado' => 'el estado del comentario',
        'nombre' => 'el nombre',
        'orden' => 'el orden',
        'password' => 'la contraseña',
        'password_actual' => 'la contraseña actual',
        'post_id' => 'la publicación',
        'q' => 'la búsqueda',
        'rol' => 'el rol',
        'tag_id' => 'la etiqueta',
        'team_id' => 'el equipo',
        'temporada' => 'la temporada',
        'tipo' => 'el tipo de archivo',
        'titulo' => 'el título',
        'type_id' => 'el tipo',
        'url' => 'el enlace',
        'user_id' => 'el usuario',
    ],

];
