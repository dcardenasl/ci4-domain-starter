# Validation System

This project uses a layered validation strategy. The primary entry point for validation is the **DTO Layer**.

## Validation Layers

1. **DTO Validation:** (Request DTOs) Handled via `validateOrFail()` in the constructor.
2. **Model Validation:** (`Model::$validationRules`) Ensures database integrity.
3. **Business Rules:** (Service logic) Domain-specific decisions.

---

## DTO Auto-Validation

Request DTOs are the "gatekeepers". They ensure that no invalid data enters the service layer.

```php
readonly class RegisterRequestDTO implements DataTransferObjectInterface
{
    public function __construct(array $data)
    {
        // Triggers CI4 validation against central rules
        validateOrFail($data, 'auth', 'register');

        $this->email = $data['email'];
        // ...
    }
}
```

### Benefits
- **Make illegal states unrepresentable:** You cannot have a DTO instance with invalid data.
- **Fail Fast:** The request is rejected as soon as the DTO is instantiated in the controller.
- **Consistency:** The same rules apply regardless of where the DTO is created (API, CLI, etc.).

---

## Domain/Action Contract

Validation rules are centralized in `app/Validations/` and registered in `InputValidationService`.

Example Domains: `auth`, `user`, `file`, `token`, `audit`, `api_key`.

---

## Common Rules Used

- `required`, `permit_empty`
- `valid_email_idn`
- `strong_password` (Custom rule)
- `max_length[N]`, `min_length[N]`
- `is_natural_no_zero` (For IDs and counts)
