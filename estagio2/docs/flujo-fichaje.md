# Flujo de fichaje: Reglas de negocio que deberán cubrir los tests unitarios.

1. **UI**: la página `imports/ui/pages/RegistrarTiempo.jsx` monta `IndexRegister`.
2. **Selector de fecha**: `DatePickerSelect` actualiza `DateProvider` (`selectedDate`, `rangeWeek`).
3. **Contexto**: `CheckinProvider` carga los registros del día con `CheckinService.getByDate({ date })`.
4. **Acción de fichar**:
   - El botón principal en `CheckInDaily` llama a `CheckinService.register`.
   - En modo manual (`EntradaManual`) se validan las horas, que la fecha no sea futura y los proyectos; después se llama a `CheckinService.registerManual`.
5. **Permisos**: `usePermissions` controla la visibilidad de la entrada manual (`allowManual`), proyectos (`allowProjects`) y registro de dispositivo (`allowDeviceRegistration` junto con `deviceUtils`).
6. **Post‑condición**: se refresca `CheckinProvider` para mostrar el último estado y el tiempo acumulado (`TotalTimeCalculator`).

Reglas de negocio cubiertas por tests:

- `registerManual` propaga excepciones si la llamada a la API falla.
- `getByDate` y `getByDates` devuelven un payload con `code` y `message` cuando hay errores controlados.
