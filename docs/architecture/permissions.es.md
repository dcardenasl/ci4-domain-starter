# Arquitectura de Permisos y Flujo de Autenticación (RBAC)

Este documento detalla la estructura y el comportamiento de la seguridad en el ecosistema basado en `ci4-platform`.

## Flujo de Permisos Cruzados

La arquitectura de seguridad separa la **interfaz de administración** (Admin/BFF) del **servicio de dominio** (Domain App). Esto introduce una distinción fundamental en el registro de permisos:

1. **Aplicación `self` (ID 1 - Hub/Admin UI)**:
   - Se encarga de controlar si el usuario logueado en la interfaz tiene autorización para ver/navegar en los diferentes módulos (por ejemplo, pintar el enlace de "Plantillas de Correo" en el menú lateral).
   - Los controles de la UI usan la directiva `has_permission('mi.permiso')` buscando este registro bajo la app con ID 1.

2. **Aplicación del Dominio (ID > 1 - e.g. `newsletter` / `catalog`)**:
   - Se encarga de proteger la API real contra la cual opera el BFF o el Admin.
   - El middleware del Dominio (`DomainAuthFilter`) intercepta el token JWT enviado y lo introspecta contra el Hub pidiendo los permisos específicos de esa aplicación de dominio (e.g. `newsletter`).

> [!IMPORTANT]
> Para que un módulo de dominio funcione de extremo a extremo, sus permisos (e.g. `newsletter.emailtemplates.read`) **deben estar registrados bajo ambas aplicaciones** (ID 1 para el Admin UI y el ID del dominio para la API) y vinculados a los roles correspondientes de los usuarios.

---

## Sincronización Automática en Desarrollo (DX)

El comando de consola `domain:sync-permissions` gestiona esta complejidad localmente:

```bash
php spark domain:sync-permissions --mirror-to-self --assign-to-role=superadmin
```

### Comportamiento del Comando

1. **Registro del Dominio** — registra todos los permisos definidos en `Config\DomainPermissions::PERMISSIONS` para su propia aplicación usando su `hub.apiKey` vía `POST /api/v1/iam/self-permissions`.
2. **Mirroring a `self`** — registra los mismos permisos bajo la aplicación `self` (ID 1) en el Hub para que queden disponibles en la interfaz del Administrador.
3. **Auto-Mint de Token (entorno local)** — en entorno `development`, el comando localiza el `.env` del Hub en directorios hermanos, lee sus credenciales de base de datos y `JWT_SECRET_KEY`, y **genera de forma autónoma un token superadmin temporal** para completar el enlace de permisos sin requerir captura manual del token.
4. **Vaciado de Caché** — al finalizar la sincronización, el comando ejecuta automáticamente `cache:clear` en el dominio, el Hub y el Admin si detecta sus directorios correspondientes.

---

## Gestión de Caché de Introspección

Para optimizar el rendimiento, el Hub cachea la resolución de permisos de cada usuario bajo la clave `iam_eff_perms_{userId}_{appId}` por **60 segundos**.

### Implicaciones en Desarrollo

- Si registras un nuevo permiso mediante el comando sync, es posible que no lo veas reflejado de inmediato en el navegador.
- **Solución**: el comando sync limpia esta caché de forma automática en desarrollo. Si cambias roles o permisos a mano, fuerza el limpiado corriendo `php spark cache:clear` en el proyecto del Hub.
