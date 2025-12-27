import LicenseService from '../licenseService';
import axiosClient from '../axiosClient';

describe('LicenseService', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('calls endpoints and returns data', async () => {
    axiosClient.post.mockResolvedValue({ data: { ok: true } });

    await LicenseService.get({ year: 2024 });
    await LicenseService.getOne({ id: 1 });
    await LicenseService.register({ type: 'vacation' });
    await LicenseService.edit({ id: 1, comments: 'x' });
    await LicenseService.deleteDocument(10);
    await LicenseService.pendingSummary();
    await LicenseService.pendingList({ limit: 5 });
    await LicenseService.pendingList();

    expect(axiosClient.post).toHaveBeenCalledWith('/license/getByYear', { year: 2024 });
    expect(axiosClient.post).toHaveBeenCalledWith('/license/getOne', { id: 1 });
    expect(axiosClient.post).toHaveBeenCalledWith('/license/create', { type: 'vacation' });
    expect(axiosClient.post).toHaveBeenCalledWith('/license/edit', { id: 1, comments: 'x' });
    expect(axiosClient.post).toHaveBeenCalledWith('/license/delete-file', { documentId: 10 });
    expect(axiosClient.post).toHaveBeenCalledWith('/license/pending-summary', {});
    expect(axiosClient.post).toHaveBeenCalledWith('/license/pending-list', { limit: 5 });
    expect(axiosClient.post).toHaveBeenCalledWith('/license/pending-list', {});
  });
});
