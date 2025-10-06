const weekDayNames = ["lunes", "martes", "miércoles", "jueves", "viernes", "sábado", "domingo"];

export const getWeekdayLabel = (index, useShort = false) => {
    const name = weekDayNames[index % 7];
    return useShort ? name.slice(0, 3) : name;
};
