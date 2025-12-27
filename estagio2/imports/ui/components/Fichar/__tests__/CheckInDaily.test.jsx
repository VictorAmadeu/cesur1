import React from 'react';
import { render, screen } from '@testing-library/react';
import CheckInDaily from '../CheckInDaily';

const mockUsePermissions = jest.fn();
const mockUseProjects = jest.fn();
const mockUseCheckin = jest.fn();

const mockButtonRegister = jest.fn(() => <div data-testid="button-register" />);
const mockProjectSelector = jest.fn(() => <div data-testid="project-selector" />);
const mockEstadoActual = jest.fn(() => <div data-testid="estado-actual" />);
const mockTotalTime = jest.fn(() => <div data-testid="total-time" />);
const mockDailyRecords = jest.fn(() => <div data-testid="daily-records" />);

jest.mock('/imports/context/permissionsContext', () => ({
  usePermissions: () => mockUsePermissions()
}));

jest.mock('../hooks/useProjects', () => ({
  __esModule: true,
  default: () => mockUseProjects()
}));

jest.mock('/imports/provider/checkIn', () => ({
  useCheckin: () => mockUseCheckin()
}));

jest.mock('../components/ButtonRegister', () => ({
  __esModule: true,
  default: (props) => mockButtonRegister(props)
}));

jest.mock('../ProjectSelector', () => ({
  __esModule: true,
  default: (props) => mockProjectSelector(props)
}));

jest.mock('../components/EstadoActual', () => ({
  __esModule: true,
  default: (props) => mockEstadoActual(props)
}));

jest.mock('../components/TotalTimeCalculatorForDay', () => ({
  __esModule: true,
  default: (props) => mockTotalTime(props)
}));

jest.mock('../components/DailyRecords', () => ({
  __esModule: true,
  default: (props) => mockDailyRecords(props)
}));

describe('CheckInDaily', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockUsePermissions.mockReturnValue({ permissions: { allowProjects: true } });
    mockUseProjects.mockReturnValue({
      projects: [],
      selectedProject: null,
      setSelectedProject: jest.fn(),
      loadingProjects: false
    });
    mockUseCheckin.mockReturnValue({
      timesForDay: [],
      lastTimeForDay: null,
      loadingTimes: false
    });
  });

  it('shows loading when loadingTimes is true', () => {
    mockUseCheckin.mockReturnValue({ loadingTimes: true });

    render(<CheckInDaily />);

    expect(screen.getByText('Cargando...')).toBeInTheDocument();
  });

  it('renders ProjectSelector when allowProjects is true', () => {
    render(<CheckInDaily />);

    expect(screen.getByTestId('project-selector')).toBeInTheDocument();
  });

  it('does not render ProjectSelector when allowProjects is false', () => {
    mockUsePermissions.mockReturnValue({ permissions: { allowProjects: false } });

    render(<CheckInDaily />);

    expect(screen.queryByTestId('project-selector')).toBeNull();
  });

  it('passes isEntry from lastTimeForDay', () => {
    mockUseCheckin.mockReturnValue({
      timesForDay: [],
      lastTimeForDay: { status: '0' },
      loadingTimes: false
    });

    render(<CheckInDaily />);

    expect(mockButtonRegister).toHaveBeenCalled();
    expect(mockButtonRegister.mock.calls[0][0].isEntry).toBe('0');
  });

  it('renders DailyRecords and TotalTimeCalculatorForDay in home mode', () => {
    mockUseCheckin.mockReturnValue({
      timesForDay: [{ id: 1 }],
      lastTimeForDay: { status: '1' },
      loadingTimes: false
    });

    render(<CheckInDaily home />);

    expect(screen.getByTestId('daily-records')).toBeInTheDocument();
    expect(screen.getByTestId('total-time')).toBeInTheDocument();
  });
});
