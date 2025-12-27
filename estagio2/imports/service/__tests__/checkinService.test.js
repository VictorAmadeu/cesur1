import CheckinService from '../checkinService';
import axiosClient from '../axiosClient';

describe('CheckinService', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('get posts to license/getByYear', async () => {
    axiosClient.post.mockResolvedValue({ data: { ok: true } });

    const result = await CheckinService.get({ year: 2024 });

    expect(axiosClient.post).toHaveBeenCalledWith('/license/getByYear', { year: 2024 });
    expect(result).toEqual({ ok: true });
  });

  it('getByDate returns data on success', async () => {
    axiosClient.post.mockResolvedValue({ data: { code: 200, data: [] } });

    const result = await CheckinService.getByDate({ date: '2024-01-01' });

    expect(axiosClient.post).toHaveBeenCalledWith('timesRegister/getByDate', { date: '2024-01-01' });
    expect(result).toEqual({ code: 200, data: [] });
  });

  it('getByDate returns fallback on error', async () => {
    axiosClient.post.mockRejectedValue({
      response: { data: { message: 'bad', code: 500 } }
    });

    const result = await CheckinService.getByDate({ date: '2024-01-01' });

    expect(result).toEqual({ message: 'bad', code: 500 });
  });

  it('getByDates returns fallback on error', async () => {
    axiosClient.post.mockRejectedValue({ response: { data: {} } });

    const result = await CheckinService.getByDates({ start: '2024-01-01', end: '2024-01-02' });

    expect(result).toEqual({ message: 'Error', code: 404 });
  });

  it('register posts to timesRegister/setTime', async () => {
    axiosClient.post.mockResolvedValue({ data: { code: 200 } });

    const result = await CheckinService.register({ deviceId: 'd1' });

    expect(axiosClient.post).toHaveBeenCalledWith('timesRegister/setTime', { deviceId: 'd1' });
    expect(result).toEqual({ code: 200 });
  });

  it('registerManual throws on error', async () => {
    axiosClient.post.mockRejectedValue(new Error('boom'));

    await expect(CheckinService.registerManual({})).rejects.toThrow('boom');
  });

  it('edit posts to license/edit', async () => {
    axiosClient.post.mockResolvedValue({ data: { ok: true } });

    const result = await CheckinService.edit({ id: 1 });

    expect(axiosClient.post).toHaveBeenCalledWith('license/edit', { id: 1 });
    expect(result).toEqual({ ok: true });
  });

  it('getByJustificationStatus returns fallback on error', async () => {
    axiosClient.post.mockRejectedValue({ response: { data: { message: 'no', code: 400 } } });

    const result = await CheckinService.getByJustificationStatus({ status: 'x' });

    expect(result).toEqual({ message: 'no', code: 400 });
  });

  it('sendJustification returns fallback on error', async () => {
    axiosClient.post.mockRejectedValue({ response: { data: {} } });

    const result = await CheckinService.sendJustification({ id: 1 });

    expect(result).toEqual({ message: 'Error', code: 404 });
  });
});
