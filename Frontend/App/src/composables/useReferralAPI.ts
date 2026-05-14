import { ref } from "vue";
import axios from "axios";
import type { AxiosError } from "axios";

export interface ReferralStats {
  code: string;
  referral_link: string;
  is_valid: boolean;
  uses: number;
  max_uses: number | null;
  referral_count: number;
  referrer_credits: number;
  referrer_credits_formatted: string;
  referee_credits: number;
  referee_credits_formatted: string;
  total_credits_earned: number;
  total_credits_earned_formatted: string;
  current_credits: number;
  current_credits_formatted: string;
  allow_custom_codes: boolean;
}

export interface ReferralHistoryItem {
  id: number;
  code_id: number;
  referred_user_id: number;
  created_at: string;
  code?: string;
  email?: string;
  username?: string;
  first_name?: string;
  last_name?: string;
}

export interface ReferralHistory {
  referrals: ReferralHistoryItem[];
  total: number;
  limit: number;
  offset: number;
  credits_per_referral: number;
  credits_per_referral_formatted: string;
  total_credits_earned: number;
  total_credits_earned_formatted: string;
}

export interface ReferralCodeData {
  id: number;
  user_id: number;
  code: string;
  uses: number;
  max_uses: number | null;
  expires_at: string | null;
  created_at: string;
  updated_at: string;
  usage_count: number;
  is_valid: boolean;
  referral_link?: string;
}

export function useReferralAPI() {
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

  // Get my referral stats
  const getStats = async (): Promise<ReferralStats> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/user/billingreferrals/stats");
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to fetch stats");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Get my referral code
  const getMyCode = async (): Promise<ReferralCodeData> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/user/billingreferrals/my-code");
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to fetch code");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Update my referral code
  const updateMyCode = async (data: {
    code?: string;
    max_uses?: number | null;
    expires_at?: string | null;
  }): Promise<ReferralCodeData> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.patch("/api/user/billingreferrals/my-code", data);
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

  // Get my referrals history
  const getMyReferrals = async (
    limit: number = 50,
    offset: number = 0
  ): Promise<ReferralHistory> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/user/billingreferrals/my-referrals", {
        params: { limit, offset },
      });
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch referrals");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Track referral visit
  const trackVisit = async (code: string): Promise<boolean> => {
    try {
      const response = await axios.post("/api/billingreferrals/visit", {
        code: code.trim(),
      });
      return response.data && response.data.success;
    } catch (err) {
      console.error("Failed to track visit:", err);
      return false;
    }
  };

  return {
    loading,
    error,
    getStats,
    getMyCode,
    updateMyCode,
    getMyReferrals,
    trackVisit,
  };
}
