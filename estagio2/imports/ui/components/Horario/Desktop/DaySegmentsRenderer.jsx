const DaySegmentsRenderer = ({ daySegments = [], segments = [], blockHeight, minHour }) => {
    const renderSegment = (segment, color) => {
        const start = dayjs(`2024-01-01T${segment.start}`);
        const end = dayjs(`2024-01-01T${segment.end}`);
        const min = dayjs(`2024-01-01T${minHour}`);

        const durationInHours = end.diff(start, "minute") / 60;
        const offsetFromMin = start.diff(min, "minute") / 60;

        const height = durationInHours * blockHeight;
        const top = offsetFromMin * blockHeight;

        return (
            <div
                key={segment.id}
                className={`absolute left-1 right-1 rounded text-white text-xs px-2 py-1 shadow-md overflow-hidden`}
                style={{
                    top,
                    height,
                    backgroundColor: color,
                }}
                title={`${segment.start} - ${segment.end}`}
            >
                {segment.start} - {segment.end}
            </div>
        );
    };

    return (
        <div className="relative w-full h-full">
            {/* Render daySegments con un color, por ejemplo azul */}
            {daySegments.map((seg) => renderSegment(seg, "#3b82f6"))}

            {/* Render segments con otro color, por ejemplo naranja */}
            {segments.map((seg) => renderSegment(seg, "#f97316"))}
        </div>
    );
};
