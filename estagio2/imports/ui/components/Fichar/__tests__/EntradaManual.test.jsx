import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { EntradaManual } from '../components/EntradaManual';
import { useDate } from '../../../../provider/date';
import { useCheckin } from '/imports/provider/checkIn';
import useProjects from '../hooks/useProjects';
import { usePermissions } from '../../../../context/permissionsContext';
import AuthService from '/imports/service/authService.js';
import CheckInService from '/imports/service/checkinService.js';
import DeviceService from '/imports/service/deviceService';
import { getOrCreateDeviceId } from '/imports/utils/deviceUtils';
import { toast } from 'react-toastify';
import { useNavigate } from 'react-router-dom';

jest.mock('../../../../provider/date', () => ({
  useDate: jest.fn()
}));

jest.mock('/imports/provider/checkIn', () => ({
  useCheckin: jest.fn()
}));

jest.mock('../hooks/useProjects', () => ({
  __esModule: true,
  default: jest.fn()
}));

jest.mock('../../../../context/permissionsContext', () => ({
  usePermissions: jest.fn()
}));

jest.mock('/imports/service/authService.js', () => ({
  __esModule: true,
  default: { isAuthenticated: jest.fn() }
}));

jest.mock('/imports/service/checkinService.js', () => ({
  __esModule: true,
  default: { registerManual: jest.fn() }
}));

jest.mock('/imports/service/deviceService', () => ({
  __esModule: true,
  default: { check: jest.fn() }
}));

jest.mock('/imports/utils/deviceUtils', () => ({
  getOrCreateDeviceId: jest.fn()
}));

jest.mock('react-toastify', () => ({
  toast: {
    success: jest.fn(),
    error: jest.fn()
  }
}));

jest.mock('react-router-dom', () => ({
  ...jest.requireActual('react-router-dom'),
  useNavigate: jest.fn()
}));

jest.mock('../ProjectSelector', () => ({
  __esModule: true,
  default: () => <div data-testid="project-selector" />
}));

describe('EntradaManual', () => {
  const refreshTimes = jest.fn();
  const setSelectedProject = jest.fn();
  const navigate = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();

    useDate.mockReturnValue({
      selectedDate: new Date('2024-01-10T00:00:00Z')
    });

    useCheckin.mockReturnValue({ refreshTimes });

    useProjects.mockReturnValue({
      projects: [],
      loadingProjects: false,
      selectedProject: { value: 3 },
      setSelectedProject
    });

    usePermissions.mockReturnValue({
      permissions: { allowDeviceRegistration: false, allowProjects: false }
    });

    useNavigate.mockReturnValue(navigate);
    AuthService.isAuthenticated.mockResolvedValue({ code: '200' });
    CheckInService.registerManual.mockResolvedValue({ code: 200, message: 'ok' });
    DeviceService.check.mockResolvedValue({ code: 200 });
    getOrCreateDeviceId.mockResolvedValue('dev1');
  });

  const setTimes = (container, start, end) => {
    const inputs = container.querySelectorAll('input[type="time"]');
    fireEvent.change(inputs[0], { target: { value: start } });
    fireEvent.change(inputs[1], { target: { value: end } });
  };

  it('requires start and end times', async () => {
    const { container } = render(<EntradaManual />);

    fireEvent.click(screen.getByRole('button', { name: /registro/i }));

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalled();
    });

    expect(CheckInService.registerManual).not.toHaveBeenCalled();
  });

  it('rejects end before start', async () => {
    const { container } = render(<EntradaManual />);

    setTimes(container, '10:00', '09:00');
    fireEvent.click(screen.getByRole('button', { name: /registro/i }));

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalled();
    });

    expect(CheckInService.registerManual).not.toHaveBeenCalled();
  });

  it('rejects future date', async () => {
    useDate.mockReturnValue({
      selectedDate: new Date(Date.now() + 24 * 60 * 60 * 1000)
    });

    const { container } = render(<EntradaManual />);

    setTimes(container, '10:00', '11:00');
    fireEvent.click(screen.getByRole('button', { name: /registro/i }));

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalled();
    });

    expect(CheckInService.registerManual).not.toHaveBeenCalled();
  });

  it('requires verified device when allowDeviceRegistration is true', async () => {
    usePermissions.mockReturnValue({
      permissions: { allowDeviceRegistration: true, allowProjects: false }
    });
    DeviceService.check.mockResolvedValue({ code: 500 });

    const { container } = render(<EntradaManual />);

    setTimes(container, '10:00', '11:00');
    fireEvent.click(screen.getByRole('button', { name: /registro/i }));

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalled();
    });

    expect(CheckInService.registerManual).not.toHaveBeenCalled();
  });

  it('navigates to login when session is invalid', async () => {
    AuthService.isAuthenticated.mockResolvedValue({ code: '401' });

    const { container } = render(<EntradaManual />);

    setTimes(container, '10:00', '11:00');
    fireEvent.click(screen.getByRole('button', { name: /registro/i }));

    await waitFor(() => {
      expect(navigate).toHaveBeenCalledWith('/login');
    });

    expect(CheckInService.registerManual).not.toHaveBeenCalled();
  });

  it('submits payload without device when allowDeviceRegistration is false', async () => {
    const { container } = render(<EntradaManual />);

    setTimes(container, '10:00', '11:00');
    fireEvent.click(screen.getByRole('button', { name: /registro/i }));

    await waitFor(() => {
      expect(CheckInService.registerManual).toHaveBeenCalled();
    });

    const payload = CheckInService.registerManual.mock.calls[0][0];
    expect(payload.deviceId).toBeUndefined();
    expect(payload.project).toBeUndefined();

    expect(toast.success).toHaveBeenCalled();
    expect(refreshTimes).toHaveBeenCalled();
    expect(setSelectedProject).toHaveBeenCalledWith(false);
  });

  it('submits payload with device and project when enabled', async () => {
    usePermissions.mockReturnValue({
      permissions: { allowDeviceRegistration: true, allowProjects: true }
    });

    const { container } = render(<EntradaManual />);

    setTimes(container, '10:00', '11:00');
    fireEvent.click(screen.getByRole('button', { name: /registro/i }));

    await waitFor(() => {
      expect(CheckInService.registerManual).toHaveBeenCalled();
    });

    const payload = CheckInService.registerManual.mock.calls[0][0];
    expect(payload.deviceId).toBe('dev1');
    expect(payload.project).toBe(3);
  });

  it('shows error when backend returns non-200', async () => {
    CheckInService.registerManual.mockResolvedValue({ code: 500, message: 'fail' });

    const { container } = render(<EntradaManual />);

    setTimes(container, '10:00', '11:00');
    fireEvent.click(screen.getByRole('button', { name: /registro/i }));

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalled();
    });
  });
});
