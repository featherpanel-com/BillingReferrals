<script setup lang="ts">
import { ref, onMounted } from "vue";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { 
  Gift, 
  Loader2, 
  Users, 
  Copy, 
  CheckCircle2,
  History,
  DollarSign,
  AlertCircle,
  Link,
} from "lucide-vue-next";
import {
  useReferralAPI,
  type ReferralStats,
  type ReferralHistoryItem,
} from "@/composables/useReferralAPI";
import { useToast } from "vue-toastification";

const toast = useToast();
const { loading, error, getStats, getMyReferrals, updateMyCode } = useReferralAPI();

// Stats
const stats = ref<ReferralStats | null>(null);
const referrals = ref<ReferralHistoryItem[]>([]);
const loadingReferrals = ref(false);
const showReferrals = ref(false);

// Copy link
const copied = ref(false);

// Custom code
const customCode = ref("");
const updatingCode = ref(false);

// Load data
const loadData = async () => {
  try {
    stats.value = await getStats();
    customCode.value = stats.value?.code || "";
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load stats");
  }
};

// Load referrals
const loadReferrals = async () => {
  loadingReferrals.value = true;
  try {
    const result = await getMyReferrals(20, 0);
    referrals.value = result.referrals;
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load referrals");
  } finally {
    loadingReferrals.value = false;
  }
};

// Toggle referrals
const toggleReferrals = async () => {
  showReferrals.value = !showReferrals.value;
  if (showReferrals.value && referrals.value.length === 0) {
    await loadReferrals();
  }
};

// Copy link
const copyLink = async () => {
  if (!stats.value?.referral_link) return;
  
  try {
    await navigator.clipboard.writeText(stats.value.referral_link);
    copied.value = true;
    toast.success("Referral link copied to clipboard!");
    setTimeout(() => copied.value = false, 2000);
  } catch (err) {
    toast.error("Failed to copy link");
  }
};

// Update custom code
const handleUpdateCode = async () => {
  if (!customCode.value.trim() || !stats.value?.allow_custom_codes) return;
  
  updatingCode.value = true;
  try {
    const updated = await updateMyCode({ code: customCode.value.trim() });
    if (stats.value) {
      stats.value.code = updated.code;
      stats.value.referral_link = updated.referral_link || stats.value.referral_link;
    }
    toast.success("Referral code updated successfully!");
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to update code");
  } finally {
    updatingCode.value = false;
  }
};

// Format date
const formatDate = (dateString: string): string => {
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

onMounted(loadData);
</script>

<template>
  <div class="w-full h-full overflow-auto p-4 md:p-8 min-h-screen">
    <div class="container mx-auto max-w-5xl">
      <div class="mb-6 text-center md:text-left">
        <h1 class="text-3xl font-bold bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent">
          Referral Program
        </h1>
        <p class="text-muted-foreground mt-2">
          Invite your friends and earn credits for each signup
        </p>
      </div>

      <!-- Stats Cards -->
      <div v-if="stats" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <Card class="border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="p-6">
            <div class="flex items-center gap-4">
              <div class="p-3 rounded-lg bg-primary/10">
                <Users class="h-6 w-6 text-primary" />
              </div>
              <div>
                <div class="text-2xl font-bold">{{ stats.referral_count }}</div>
                <div class="text-sm text-muted-foreground">Friends Referred</div>
              </div>
            </div>
          </div>
        </Card>

        <Card class="border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="p-6">
            <div class="flex items-center gap-4">
              <div class="p-3 rounded-lg bg-green-500/10">
                <DollarSign class="h-6 w-6 text-green-600" />
              </div>
              <div>
                <div class="text-2xl font-bold text-green-600">{{ stats.total_credits_earned_formatted }}</div>
                <div class="text-sm text-muted-foreground">Credits Earned</div>
              </div>
            </div>
          </div>
        </Card>

        <Card class="border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="p-6">
            <div class="flex items-center gap-4">
              <div class="p-3 rounded-lg bg-blue-500/10">
                <Gift class="h-6 w-6 text-blue-600" />
              </div>
              <div>
                <div class="text-2xl font-bold text-blue-600">{{ stats.referrer_credits_formatted }}</div>
                <div class="text-sm text-muted-foreground">Per Referral</div>
              </div>
            </div>
          </div>
        </Card>
      </div>

      <!-- Referral Link Card -->
      <Card class="border-2 shadow-xl bg-card/50 backdrop-blur-sm mb-6">
        <div class="p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="p-2 rounded-lg bg-primary/10">
              <Link class="h-5 w-5 text-primary" />
            </div>
            <h2 class="text-xl font-semibold">Your Referral Link</h2>
          </div>

          <div v-if="loading" class="flex justify-center py-8">
            <Loader2 class="h-8 w-8 animate-spin" />
          </div>

          <template v-else-if="stats">
            <div class="space-y-4">
              <div class="relative">
                <Input
                  :model-value="stats.referral_link"
                  type="text"
                  readonly
                  class="h-12 text-base font-mono pr-32 bg-muted/50"
                />
                <div class="absolute right-2 top-1/2 -translate-y-1/2">
                  <Button
                    @click="copyLink"
                    :disabled="copied"
                    variant="default"
                    size="sm"
                  >
                    <CheckCircle2 v-if="copied" class="h-4 w-4 mr-2" />
                    <Copy v-else class="h-4 w-4 mr-2" />
                    {{ copied ? "Copied!" : "Copy" }}
                  </Button>
                </div>
              </div>

              <!-- Custom Code Input -->
              <div v-if="stats.allow_custom_codes" class="pt-4 border-t">
                <Label class="text-sm font-medium mb-2 block">Customize Your Code</Label>
                <div class="flex gap-3">
                  <Input
                    v-model="customCode"
                    type="text"
                    placeholder="Enter custom code (e.g., MYCODE)"
                    class="h-10 font-mono uppercase"
                    maxlength="20"
                  />
                  <Button
                    @click="handleUpdateCode"
                    :disabled="updatingCode || !customCode.trim() || customCode === stats.code"
                  >
                    <Loader2 v-if="updatingCode" class="mr-2 h-4 w-4 animate-spin" />
                    Update
                  </Button>
                </div>
                <p class="text-xs text-muted-foreground mt-2">
                  Code must be 4-20 characters, letters and numbers only
                </p>
              </div>
            </div>
          </template>

          <Alert v-if="error" variant="destructive" class="mt-4">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>{{ error }}</AlertDescription>
          </Alert>
        </div>
      </Card>

      <!-- How it Works -->
      <Card class="border-2 shadow-xl bg-card/50 backdrop-blur-sm mb-6">
        <div class="p-6">
          <h3 class="text-lg font-semibold mb-4">How it works</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 rounded-lg bg-muted/30 border border-border/50">
              <div class="flex items-center gap-2 mb-2">
                <DollarSign class="h-5 w-5 text-green-600" />
                <span class="font-medium">You Earn</span>
              </div>
              <p class="text-sm text-muted-foreground">
                Get <strong class="text-green-600">{{ stats?.referrer_credits || 0 }} credits</strong> for each friend who signs up
              </p>
            </div>
            <div class="p-4 rounded-lg bg-muted/30 border border-border/50">
              <div class="flex items-center gap-2 mb-2">
                <Gift class="h-5 w-5 text-blue-600" />
                <span class="font-medium">They Get</span>
              </div>
              <p class="text-sm text-muted-foreground">
                Your friends receive <strong class="text-blue-600">{{ stats?.referee_credits || 0 }} credits</strong> signup bonus
              </p>
            </div>
          </div>
        </div>
      </Card>

      <!-- Referrals History -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <History class="h-5 w-5 text-primary" />
            <h2 class="text-xl font-semibold">Your Referrals</h2>
          </div>
          <Button
            variant="outline"
            @click="toggleReferrals"
            :disabled="loadingReferrals"
          >
            <History class="h-4 w-4 mr-2" />
            {{ showReferrals ? "Hide" : "Show" }} Referrals
            <Badge v-if="stats?.referral_count && stats.referral_count > 0" variant="secondary" class="ml-2">
              {{ stats.referral_count }}
            </Badge>
          </Button>
        </div>

        <Card v-if="showReferrals" class="border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="p-6">
            <div v-if="loadingReferrals" class="flex justify-center py-12">
              <Loader2 class="h-8 w-8 animate-spin" />
            </div>

            <div v-else-if="referrals.length === 0" class="text-center py-12 text-muted-foreground">
              No referrals yet. Share your link to start earning credits!
            </div>

            <div v-else class="space-y-2">
              <div
                v-for="referral in referrals"
                :key="referral.id"
                class="flex items-center justify-between p-4 border rounded-lg hover:bg-accent transition-colors"
              >
                <div class="flex items-center gap-3">
                  <div class="p-2 rounded-lg bg-green-500/10">
                    <CheckCircle2 class="h-4 w-4 text-green-600" />
                  </div>
                  <div>
                    <div class="font-medium">{{ referral.username || referral.email || 'Unknown' }}</div>
                    <div class="text-sm text-muted-foreground">Joined {{ formatDate(referral.created_at) }}</div>
                  </div>
                </div>
                <Badge class="bg-green-500/10 text-green-600">
                  +{{ stats?.referrer_credits || 0 }} credits
                </Badge>
              </div>
            </div>
          </div>
        </Card>
      </div>
    </div>
  </div>
</template>
