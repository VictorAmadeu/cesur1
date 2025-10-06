export function formatTime(hours, minutes, seconds) {
    const formattedHours = hours < 10 ? "0" + hours : hours;
    const formattedMinutes = minutes < 10 ? "0" + minutes : minutes;
    const formattedSeconds = seconds < 10 ? "0" + seconds : seconds;
    return `${formattedHours}:${formattedMinutes}:${formattedSeconds}`;
  }