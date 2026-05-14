import { ref } from "vue";
import axios from "axios";
import type { AxiosError } from "axios";

export interface ReferralSettings {
  is_enabled: boolean;
  referrer_credits: number;
  referee_credits: number;
  default_max_uses: number;
  cookie_lifetime_days: number;
  allow_custom_codes: boolean;
}

export interface ReferralCodeItem {
  id: number;
  user_id: number;
  username?: string;
  email?: string;
  code: string;
  uses: number;
  max_uses: number | null;
  expires_at: string | null;
  created_at: string;
  updated_at: string;
  usage_count: number;
  is_valid: boolean;
}

export interface ReferralCodesList {
  codes: ReferralCodeItem[];
  total: number;
  limit: number;
  offset: number;
}

export interface ReferralUsageItem {
  id: number;
  code_id: number;
  referred_user_id: number;
  created_at: string;
  email?: string;
  username?: string;
  first_name?: string;
  last_name?: string;
  user_created_at?: string;
}

export interface ReferralUsageList {
  usage: ReferralUsageItem[];
  total: number;
  limit: number;
  offset: number;
}

export interface ReferralStats {
  total_codes: number;
  total_referrals: number;
  total_referrer_credits: number;
  total_referrer_credits_formatted: string;
  total_referee_credits: number;
  total_referee_credits_formatted: string;
  settings: ReferralSettings;
}

export function useReferralAdminAPI() {
  const loading = ref(false);
  const error = ref<string | null>(null);

  const handleError = (err: unknown): string => {
    if (axios.isAxiosError(err)) {
      const axiosError = err as AxiosError<{
        message?: string;
        error_message?: string;
        error?: string;
      }>;
      return (
        axiosError.response?.data?.message ||
        axiosError.response?.data?.error_message ||
        axiosError.response?.data?.error ||
        axiosError.message ||
        "An error occurred"
      );
    }
    if (err instanceof Error) {
      return err.message;
    }
    return "An unknown error occurred";
  };

  // Get settings
  const getSettings = async (): Promise<ReferralSettings> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/admin/billingreferrals/settings");
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch settings");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Update settings
  const updateSettings = async (settings: Partial<ReferralSettings>): Promise<ReferralSettings> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.patch("/api/admin/billingreferrals/settings", settings);
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to update settings");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Get all referral codes
  const getCodes = async (limit: number = 50, offset: number = 0): Promise<ReferralCodesList> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/admin/billingreferrals/codes", {
        params: { limit, offset },
      });
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch codes");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Get code by ID
  const getCode = async (id: number): Promise<ReferralCodeItem> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(`/api/admin/billingreferrals/codes/${id}`);
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch code");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Create code
  const createCode = async (data: {
    user_id: number;
    code?: string;
    max_uses?: number | null;
    expires_at?: string | null;
  }): Promise<ReferralCodeItem> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post("/api/admin/billingreferrals/codes", data);
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to create code");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Update code
  const updateCode = async (
    id: number,
    data: {
      code?: string;
      max_uses?: number | null;
      expires_at?: string | null;
    }
  ): Promise<ReferralCodeItem> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.patch(`/api/admin/billingreferrals/codes/${id}`, data);
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to update code");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Delete code
  const deleteCode = async (id: number): Promise<void> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.delete(`/api/admin/billingreferrals/codes/${id}`);
      if (!response.data || !response.data.success) {
        throw new Error("Failed to delete code");
      }
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Get code usage
  const getCodeUsage = async (
    id: number,
    limit: number = 50,
    offset: number = 0
  ): Promise<ReferralUsageList> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(`/api/admin/billingreferrals/codes/${id}/usage`, {
        params: { limit, offset },
      });
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch usage");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Get stats
  const getStats = async (): Promise<ReferralStats> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/admin/billingreferrals/stats");
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch stats");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  return {
    loading,
    error,
    getSettings,
    updateSettings,
    getCodes,
    getCode,
    createCode,
    updateCode,
    deleteCode,
    getCodeUsage,
    getStats,
  };
}
