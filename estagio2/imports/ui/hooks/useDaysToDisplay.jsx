import { useMemo } from "react";
import dayjs from "../../utils/dayjsConfig";

const useDaysToDisplay = ({ isMobile, selectedDate, rangeWeek }) => {
    return useMemo(() => {
        const start = dayjs(isMobile ? selectedDate : rangeWeek.start);
        const end = dayjs(isMobile ? selectedDate : rangeWeek.end);
        const result = [];
        for (let i = 0; i <= end.diff(start, "day"); i++) {
            result.push(start.add(i, "day"));
        }
        return result;
    }, [isMobile, selectedDate, rangeWeek]);
};

export default useDaysToDisplay;
