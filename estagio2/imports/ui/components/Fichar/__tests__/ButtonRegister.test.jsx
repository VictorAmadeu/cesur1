import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import ButtonRegister from '../components/ButtonRegister';
import CheckinService from '/imports/service/checkinService';
import { getStoredDeviceId } from '/imports/utils/deviceUtils';
import { toast } from 'react-toastify';
import { useCheckin } from '../../../../provider/checkIn';

jest.mock('/imports/service/checkinService', () => ({
  __esModule: true,
  default: { register: jest.fn() }
}));

jest.mock('/imports/utils/deviceUtils', () => ({
  getStoredDeviceId: jest.fn()
}));

jest.mock('react-toastify', () => ({
  toast: {
    success: jest.fn(),
    error: jest.fn()
  }
}));

jest.mock('../../../../provider/checkIn', () => ({
  useCheckin: jest.fn()
}));

describe('ButtonRegister', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    useCheckin.mockReturnValue({ refreshTimes: jest.fn() });
  });

  it('shows entry label when isEntry is truthy', () => {
    render(<ButtonRegister isEntry selectedProject={null} />);

    expect(screen.getByRole('button', { name: /fichar entrada/i })).toBeInTheDocument();
  });

  it('shows exit label when isEntry is falsy', () => {
    render(<ButtonRegister isEntry={false} selectedProject={null} />);

    expect(screen.getByRole('button', { name: /fichar salida/i })).toBeInTheDocument();
  });

  it('registers time and shows success', async () => {
    const refreshTimes = jest.fn();
    useCheckin.mockReturnValue({ refreshTimes });

    getStoredDeviceId.mockResolvedValue({ deviceId: 'dev1' });
    CheckinService.register.mockResolvedValue({ code: 200, message: 'ok' });

    render(<ButtonRegister isEntry selectedProject={{ value: 7 }} />);

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(CheckinService.register).toHaveBeenCalledWith({
        project: 7,
        deviceId: 'dev1'
      });
    });

    expect(toast.success).toHaveBeenCalledWith('ok', { position: 'top-center' });
    expect(refreshTimes).toHaveBeenCalled();
  });

  it('shows error when backend returns non-200', async () => {
    const refreshTimes = jest.fn();
    useCheckin.mockReturnValue({ refreshTimes });

    getStoredDeviceId.mockResolvedValue({ deviceId: 'dev1' });
    CheckinService.register.mockResolvedValue({ code: 500, message: 'fail' });

    render(<ButtonRegister isEntry selectedProject={null} />);

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(CheckinService.register).toHaveBeenCalled();
    });

    expect(toast.error).toHaveBeenCalledWith('fail', { position: 'top-center' });
    expect(refreshTimes).toHaveBeenCalled();
  });

  it('handles exceptions', async () => {
    const refreshTimes = jest.fn();
    useCheckin.mockReturnValue({ refreshTimes });

    getStoredDeviceId.mockResolvedValue({ deviceId: 'dev1' });
    CheckinService.register.mockRejectedValue(new Error('boom'));

    render(<ButtonRegister isEntry selectedProject={null} />);

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalled();
    });

    expect(refreshTimes).toHaveBeenCalled();
  });
});
