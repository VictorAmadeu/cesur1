import AuthService from '../authService';
import axiosClient from '../axiosClient';
import Cookies from 'js-cookie';

describe('AuthService', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('login uses 8h expiration when remember_me is false', async () => {
    jest.useFakeTimers();
    jest.setSystemTime(new Date('2024-01-01T00:00:00Z'));

    const response = { data: { token: 'tok', name: 'User' } };
    axiosClient.post.mockResolvedValue(response);

    const result = await AuthService.login({ email: 'a@b.com', _remember_me: false });

    expect(result).toBe(response);
    expect(axiosClient.post).toHaveBeenCalledWith('/login_check', {
      email: 'a@b.com',
      _remember_me: false
    });

    const tokenCall = Cookies.set.mock.calls.find((call) => call[0] === 'tokenIntranEK');
    const nameCall = Cookies.set.mock.calls.find((call) => call[0] === 'name');

    expect(tokenCall).toBeTruthy();
    expect(nameCall).toBeTruthy();
    expect(tokenCall[2].secure).toBe(true);
    expect(nameCall[2].secure).toBe(true);

    const expires = tokenCall[2].expires;
    expect(expires.getTime() - Date.now()).toBe(8 * 60 * 60 * 1000);

    jest.useRealTimers();
  });

  it('login uses 30d expiration when remember_me is true', async () => {
    jest.useFakeTimers();
    jest.setSystemTime(new Date('2024-01-01T00:00:00Z'));

    const response = { data: { token: 'tok', name: 'User' } };
    axiosClient.post.mockResolvedValue(response);

    await AuthService.login({ email: 'a@b.com', _remember_me: true });

    const tokenCall = Cookies.set.mock.calls.find((call) => call[0] === 'tokenIntranEK');
    const expires = tokenCall[2].expires;
    expect(expires.getTime() - Date.now()).toBe(30 * 24 * 60 * 60 * 1000);

    jest.useRealTimers();
  });

  it('login logs and rethrows on error', async () => {
    axiosClient.post.mockRejectedValue(new Error('fail'));

    await expect(
      AuthService.login({ email: 'a@b.com', _remember_me: false })
    ).rejects.toThrow('fail');

    expect(console.error).toHaveBeenCalled();
  });

  it('logout clears cookies', () => {
    AuthService.logout();

    expect(Cookies.remove).toHaveBeenCalledWith('tokenIntranEK');
    expect(Cookies.remove).toHaveBeenCalledWith('permissions');
    expect(Cookies.remove).toHaveBeenCalledWith('role');
  });

  it('isAuthenticated returns data on success', async () => {
    axiosClient.post.mockResolvedValue({ data: { code: 200, ok: true } });

    const result = await AuthService.isAuthenticated();

    expect(axiosClient.post).toHaveBeenCalledWith('/global/keepAlive');
    expect(result).toEqual({ code: 200, ok: true });
  });

  it('isAuthenticated returns fallback on error', async () => {
    axiosClient.post.mockRejectedValue(new Error('boom'));

    const result = await AuthService.isAuthenticated();

    expect(result).toEqual({ message: 'boom', code: 404 });
  });

  it('fetchPermissions returns data on success', async () => {
    axiosClient.post.mockResolvedValue({ data: { code: 200, permissions: {} } });

    const result = await AuthService.fetchPermissions();

    expect(axiosClient.post).toHaveBeenCalledWith('/companies/permissions');
    expect(result).toEqual({ code: 200, permissions: {} });
  });

  it('fetchPermissions throws on error', async () => {
    axiosClient.post.mockRejectedValue(new Error('nope'));

    await expect(AuthService.fetchPermissions()).rejects.toThrow('nope');
    expect(console.error).toHaveBeenCalled();
  });

  it('getAllPermissions handles missing and invalid cookies', () => {
    Cookies.get.mockReturnValueOnce(undefined);
    expect(AuthService.getAllPermissions()).toEqual([]);

    Cookies.get.mockReturnValueOnce('bad-json');
    expect(AuthService.getAllPermissions()).toEqual([]);
  });

  it('getRole handles missing and invalid cookies', () => {
    Cookies.get.mockReturnValueOnce(undefined);
    expect(AuthService.getRole()).toEqual([{ message: 'Rol no disponibles.', code: 404 }]);

    Cookies.get.mockReturnValueOnce('bad-json');
    const result = AuthService.getRole();
    expect(result[0].code).toBe(404);
  });

  it('hasPermission returns true when permission exists', () => {
    Cookies.get.mockReturnValueOnce(JSON.stringify([{ canView: true }]));
    expect(AuthService.hasPermission('canView')).toBe(true);

    Cookies.get.mockReturnValueOnce('bad-json');
    expect(AuthService.hasPermission('canView')).toBe(false);
  });

  it('password endpoints call axiosClient', async () => {
    axiosClient.post.mockResolvedValue({ data: { ok: true } });

    await AuthService.forgetPassword('a@b.com');
    await AuthService.changePassword({ pass: 'x' });
    await AuthService.changePasswordFirstTime({ pass: 'y' });

    expect(axiosClient.post).toHaveBeenCalledWith('/reset-password/request', { email: 'a@b.com' });
    expect(axiosClient.post).toHaveBeenCalledWith('/reset-password/change', { pass: 'x' });
    expect(axiosClient.post).toHaveBeenCalledWith('/reset-password/change-first', { pass: 'y' });
  });
});
