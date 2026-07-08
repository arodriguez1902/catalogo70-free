# Changelog - CodeCatalogo Pro

## [1.1.0] - 2025-12-30

### Fixed
- **Sistema de Licencias:** Corregido el formato de envío del license key a la API
  - La API ahora recibe el license key en formato original con guiones (XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX)
  - Eliminada la conversión a minúsculas y eliminación de guiones que causaba errores de validación
  - Actualizado en métodos: `activate_license()`, `validate_license()`, `deactivate_license()`

### Changed
- Actualizada versión del sistema de licencias a 4.0-fixed-final
- Limpiada la interfaz de administración de licencias
  - Eliminadas herramientas de debug temporales
  - Eliminado panel de "Información de Debug"
  - Eliminado botón "Sincronizar Ahora"
  - Eliminadas "Herramientas de Diagnóstico"
- Página de activación simple ocultada del menú (aún accesible por URL directa si se necesita para soporte)

### Technical Details
- El license key ahora se envía a la API exactamente como se ingresa (con guiones y mayúsculas)
- Mejorados los mensajes de error para códigos HTTP comunes (429, 403, 404, 500)
- Simplificada la lógica de activación eliminando validaciones de formato redundantes

---

## [1.0.0] - 2025-12-28

### Added
- Lanzamiento inicial del plugin
- Sistema de gestión de licencias con API externa
- Límites para versión FREE (50 productos, 10 categorías, 5 campos)
- Validación automática de licencias cada 7 días
- Sistema de CTAs (WhatsApp y Formularios)
- Campos personalizados para productos
- Importación/Exportación de productos
- Optimización SEO con Schema.org
