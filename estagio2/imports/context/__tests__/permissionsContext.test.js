import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import Cookies from 'js-cookie';
import { PermissionsProvider, usePermissions } from '../permissionsContext';
import AuthService from '../../service/authService';

jest.mock('../../service/authService', () => ({
  __esModule: true,
  default: {
    fetchPermissions: jest.fn()
  }
}));

const Consumer = () => {
  const { permissions, role, fetchPermissions } = usePermissions();
  const [result, setResult] = React.useState(null);

  return (
    <div>
      <div data-testid="permissions">{JSON.stringify(permissions)}</div>
      <div data-testid="role">{JSON.stringify(role)}</div>
      <button onClick={async () => setResult(await fetchPermissions())}>fetch</button>
      <div data-testid="result">{result ? result.code : 'none'}</div>
    </div>
  );
};

describe('PermissionsContext', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('stores permissions and role on success', async () => {
    AuthService.fetchPermissions.mockResolvedValue({
      code: 200,
      permissions: { allowManual: true },
      role: { name: 'admin' },
      message: 'ok'
    });

    render(
      <PermissionsProvider>
        <Consumer />
      </PermissionsProvider>
    );

    await waitFor(() => {
      expect(Cookies.set).toHaveBeenCalledWith('role', JSON.stringify({ name: 'admin' }));
      expect(Cookies.set).toHaveBeenCalledWith('permissions', JSON.stringify({ allowManual: true }));
    });

    const permissionsText = screen.getByTestId('permissions').textContent;
    const roleText = screen.getByTestId('role').textContent;

    expect(permissionsText).toContain('allowManual');
    expect(roleText).toContain('admin');
  });

  it('returns code when backend responds with non-200', async () => {
    AuthService.fetchPermissions.mockResolvedValue({
      code: 500,
      permissions: {},
      role: null,
      message: 'no'
    });

    render(
      <PermissionsProvider>
        <Consumer />
      </PermissionsProvider>
    );

    fireEvent.click(screen.getByText('fetch'));

    await waitFor(() => {
      expect(screen.getByTestId('result').textContent).toBe('500');
    });
  });

  it('returns 404 on error', async () => {
    AuthService.fetchPermissions.mockRejectedValue(new Error('boom'));

    render(
      <PermissionsProvider>
        <Consumer />
      </PermissionsProvider>
    );

    fireEvent.click(screen.getByText('fetch'));

    await waitFor(() => {
      expect(screen.getByTestId('result').textContent).toBe('404');
    });
  });
});
