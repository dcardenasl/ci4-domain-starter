# Guía de Extensión


## Añadir un Nuevo Recurso CRUD

Proceso completo paso a paso:

1. **Scaffold primero** - `bash bin/make-crud.sh Product Catalog 'name:string:required|searchable,price:decimal:required' yes products`
2. **Validar scaffold** - `php spark module:check Product --domain Catalog`
3. **Ejecutar migración(es)** - `php spark migrate` (generada por scaffold)
4. **Reiniciar servidor** - `pkill -f 'spark serve'; php spark serve --port 8080 &` (para que se carguen las rutas nuevas)
5. **Alinear entity/model** - campos, casts, validación, traits de query
6. **Cerrar contratos DTO** - Request/Response DTOs + atributos OpenAPI
7. **Cerrar servicio** - lógica pura + estrategia de repositorio
8. **Registrar dependencias** - actualizar `app/Config/Services.php` cuando aplique
9. **Crear/verificar rutas** - actualizar `app/Config/Routes.php`
10. **Añadir archivos de idioma** - `app/Language/{lang}/Products.php`
11. **Escribir tests** - pruebas Unit, Integration, Feature
12. **Ejecutar quality/docs gates** - `composer quality` + `php spark swagger:generate`

> Para entornos interactivos puedes usar `php spark make:crud Product --domain Catalog` y el motor te consultará cada campo. En entornos no-TTY el wrapper es obligatorio — el shell puede consumir los pipes en `--fields` y el motor queda esperando entrada interactiva.

## Inicio Rápido

Ver el [`../../README.es.md`](../../README.es.md) raíz § "Inicio rápido" y § "Añadir un nuevo módulo CRUD" para un recorrido completo con ejemplos de código.

El repo incluye un módulo de ejemplo (`DemoProduct` en el dominio `Catalog`). Revisa su estructura (DTOs, controladores, servicios y tests) para entender cómo lucen los artefactos generados y usa `php spark module:check <Resource> --domain <Domain>` para validar tus propios módulos.

El comando `make:crud` genera archivos de migración, entity/model/interface/service/controller/DTOs/docs/i18n/tests, utilizando un esquema único para asegurar la sincronización en todas las capas.

## Extender un CRUD hasta un aggregate

`make:crud` es el bootstrap correcto para un **recurso plano**. No es el estado final de un aggregate que necesita acciones de workflow, hijos anidados, arrays de relaciones o respuestas enriquecidas.

Usa esta escalera:

1. Empieza con `make:crud` y deja el módulo generado en verde con `php spark module:check`.
2. Mantén el controller generado como shell HTTP y agrega solo los endpoints extra que realmente necesites.
3. Empuja la lógica de aggregate al service, no al controller.
4. Mantén la persistencia explícita: el CRUD simple sigue en el camino model/repository generado; el sync de relaciones y de child resources se coordina en la transacción del service.
5. Agrega Feature tests para cada endpoint custom y para cada invariante del aggregate que introduzcas.

### Cuándo el scaffold deja de alcanzar

Pasa de “CRUD generado” a “aggregate extension” cuando el recurso necesita cualquiera de estos casos:

- acciones custom como `publish`, `archive`, `approve`
- sub-recursos anidados como `/items/{id}/media`
- arrays de relaciones en el payload como `tag_ids[]` o `media[]` embebido
- sincronización post-save entre varias tablas
- response enrichment que devuelva hijos, campos calculados o resúmenes denormalizados

### Patrón recomendado

**Controller**

- Mantén los endpoints CRUD estándar sobre `handleRequest()` cuando todavía calcen con el flujo generado.
- Agrega métodos explícitos solo para los endpoints no-CRUD (`publish`, `mediaIndex`, `mediaStore`, etc.).
- El controller sigue haciendo solo HTTP → DTO/service call → response.

**DTOs**

- Agrega request DTOs dedicados para acciones custom o payloads anidados en lugar de sobrecargar indefinidamente los DTOs generados de create/update.
- Mantén honestos los response DTOs: si el aggregate ahora devuelve hijos o datos calculados, extiende el contrato explícitamente.

**Service**

- Trata al service como el boundary del aggregate.
- Valida aquí las invariantes cross-field y cross-table.
- Coordina aquí el sync de relaciones.
- Envuelve en una sola transacción las escrituras multi-tabla.

**Routes**

- Conserva las rutas CRUD generadas.
- Agrega de forma explícita en el archivo de rutas del dominio las rutas custom y anidadas cuando el aggregate crezca más allá del CRUD plano.

### Forma concreta del ejemplo

Para un recurso tipo `Item`, la progresión normal suele verse así:

1. Scaffold de `Item` con campos escalares y la tabla principal.
2. Mantener `index/show/store/update/delete` generados.
3. Agregar `publish(string $id)` como método custom de controller y service cuando el aggregate gana un workflow state.
4. Agregar endpoints anidados `/items/{id}/media` cuando los child records dejan de caber limpiamente en el payload CRUD padre.
5. Mover el sync de `tag_ids[]` / `media[]` a la transacción del service después de crear o actualizar el registro base.
6. Extender el response DTO para que `show()` pueda devolver item + resumen de tags/media en un solo contrato.

### Checklist práctico después del scaffold

- Actualizar request DTOs cuando el payload deja de ser solo escalar.
- Actualizar el service cuando la escritura toca más de una tabla.
- Actualizar rutas por cada acción custom y recurso anidado.
- Actualizar response DTOs cuando la forma de la API crece más allá de los campos generados.
- Agregar o actualizar primero Feature tests para rutas custom, y luego cobertura Unit/Integration para la lógica del service y la persistencia.

El scaffold sigue siendo valioso incluso en estos casos: te da la estructura base, naming, i18n, tests y wiring de servicios. El trabajo de extensión debe profundizar esa estructura, no reemplazarla ad hoc.

## Añadir Filtros Personalizados

```php
// 1. Crear filtro
// app/Filters/MyFilter.php
class MyFilter implements FilterInterface { ... }

// 2. Registrar alias
// app/Config/Filters.php
public array $aliases = [
    'myfilter' => \App\Filters\MyFilter::class,
];

// 3. Usar en rutas
$routes->group('', ['filter' => 'myfilter'], function ($routes) {
    // ...
});
```

## Añadir Excepciones Personalizadas

```php
// app/Exceptions/PaymentRequiredException.php
class PaymentRequiredException extends ApiException
{
    protected int $statusCode = 402;
}
```
