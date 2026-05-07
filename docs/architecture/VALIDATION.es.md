# Sistema de Validación

Este proyecto utiliza una estrategia de validación por capas. El punto de entrada principal para la validación es la **Capa DTO**.

## Capas de Validación

1. **Validación DTO:** (Request DTOs) Manejada vía `validateOrFail()` en el constructor.
2. **Validación de Modelo:** (`Model::$validationRules`) Garantiza la integridad de la base de datos.
3. **Reglas de Negocio:** (Lógica de servicio) Decisiones específicas del dominio.

---

## Auto-Validación de DTOs

Los Request DTOs son los "guardianes". Aseguran que ningún dato inválido llegue a la capa de servicio.

```php
readonly class RegisterRequestDTO implements DataTransferObjectInterface
{
    public function __construct(array $data)
    {
        // Dispara la validación de CI4 contra las reglas centrales
        validateOrFail($data, 'auth', 'register');

        $this->email = $data['email'];
        // ...
    }
}
```

### Beneficios
- **Hacer que los estados ilegales sean irrepresentables:** No se puede tener una instancia de DTO con datos inválidos.
- **Fallar Rápido (Fail Fast):** La petición se rechaza en cuanto se instancia el DTO en el controlador.
- **Consistencia:** Se aplican las mismas reglas independientemente de dónde se cree el DTO (API, CLI, etc.).

---

## Contrato Dominio/Acción

Las reglas de validación están centralizadas en `app/Validations/` y registradas en `InputValidationService`.

Dominios de ejemplo: `auth`, `user`, `file`, `token`, `audit`, `api_key`.

---

## Reglas Comunes en Uso

- `required`, `permit_empty`
- `valid_email_idn`
- `strong_password` (Regla personalizada)
- `max_length[N]`, `min_length[N]`
- `is_natural_no_zero` (Para IDs y contadores)
