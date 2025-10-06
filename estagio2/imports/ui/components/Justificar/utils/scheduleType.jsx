const ScheduleType = {
  NORMAL: 'normal',
  EXTRA_BEFORE: 'extra_before',
  EXTRA_AFTER: 'extra_after',
  LATE_ENTRY: 'late_entry',
  EARLY_EXIT: 'early_exit',
  MANUAL: 'manual'
};

export const ScheduleTypeLabels = {
  [ScheduleType.NORMAL]: 'Normal',
  [ScheduleType.EXTRA_BEFORE]: 'Fichaje antes del horario laboral',
  [ScheduleType.EXTRA_AFTER]: 'Fichaje después del horario laboral',
  [ScheduleType.LATE_ENTRY]: 'Llegada tarde',
  [ScheduleType.EARLY_EXIT]: 'Salida anticipada',
  [ScheduleType.MANUAL]: 'Manual'
};
