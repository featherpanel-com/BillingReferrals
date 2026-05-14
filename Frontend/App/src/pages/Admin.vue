<script setup lang="ts">
import { ref, onMounted, watch } from "vue";
import { Card } from "@/components/ui/card";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import {
  Loader2,
  Settings,
  Gift,
  Save,
  Users,
  ChevronLeft,
  ChevronRight,
  DollarSign,
  CheckCircle2,
  X,
  QrCode,
} from "lucide-vue-next";
import {
  useReferralAdminAPI,
  type ReferralSettings,
  type ReferralCodeItem,
  type ReferralUsageItem,
  type ReferralStats,
} from "@/composables/useReferralAdminAPI";
import { useToast } from "vue-toastification";

const toast = useToast();
const {
  getSettings,
  updateSettings,
  getCodes,
  getCodeUsage,
  getStats,
} = useReferralAdminAPI();

// Settings
const settings = ref<ReferralSettings | null>(null);
const savingSettings = ref(false);

// Stats
const stats = ref<ReferralStats | null>(null);

// Codes
const codes = ref<ReferralCodeItem[]>([]);
const codesPage = ref(1);
const codesTotal = ref(0);
const loadingCodes = ref(false);

// Code usage
const selectedCode = ref<ReferralCodeItem | null>(null);
const codeUsage = ref<ReferralUsageItem[]>([]);
const usagePage = ref(1);
const usageTotal = ref(0);
const loadingUsage = ref(false);
const showUsage = ref(false);

// Active tab
const activeTab = ref("codes");

// Watch for tab changes
watch(activeTab, (newTab) => {
  if (newTab === "settings" && !settings.value) {
    loadSettings();
  } else if (newTab === "codes" && codes.value.length === 0) {
    loadCodes();
  }
});

const loadSettings = async () => {
  try {
    settings.value = await getSettings();
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load settings");
  }
};

const loadStats = async () => {
  try {
    stats.value = await getStats();
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load stats");
  }
};

const saveSettings = async () => {
  if (!settings.value) return;

  savingSettings.value = true;
  try {
    settings.value = await updateSettings(settings.value);
    toast.success("Settings saved successfully!");
    await loadStats();
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to save settings");
  } finally {
    savingSettings.value = false;
  }
};

const loadCodes = async (page: number = 1) => {
  codesPage.value = page;
  loadingCodes.value = true;
  try {
    const result = await getCodes(20, (page - 1) * 20);
    codes.value = result.codes;
    codesTotal.value = result.total;
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load codes");
  } finally {
    loadingCodes.value = false;
  }
};

const loadCodeUsage = async (code: ReferralCodeItem, page: number = 1) => {
  selectedCode.value = code;
  usagePage.value = page;
  showUsage.value = true;
  loadingUsage.value = true;
  try {
    const result = await getCodeUsage(code.id, 20, (page - 1) * 20);
    codeUsage.value = result.usage;
    usageTotal.value = result.total;
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load usage");
  } finally {
    loadingUsage.value = false;
  }
};

const closeUsage = () => {
  showUsage.value = false;
  selectedCode.value = null;
  codeUsage.value = [];
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

onMounted(() => {
  loadCodes();
  loadStats();
});
</script>

<template>
  <div class="w-full h-full overflow-auto p-4 md:p-8 min-h-screen">
    <div class="container mx-auto max-w-6xl">
      <div class="mb-6 text-center md:text-left">
        <h1 class="text-3xl font-bold bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent">
          Referral System - Admin
        </h1>
        <p class="text-muted-foreground mt-2">
          Manage referral settings and view statistics
        </p>
      </div>

      <!-- Stats Cards -->
      <div v-if="stats" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <Card class="border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="p-6">
            <div class="flex items-center gap-4">
              <div class="p-3 rounded-lg bg-primary/10">
                <QrCode class="h-6 w-6 text-primary" />
              </div>
              <div>
                <div class="text-2xl font-bold">{{ stats.total_codes }}</div>
                <div class="text-sm text-muted-foreground">Total Codes</div>
              </div>
            </div>
          </div>
        </Card>

        <Card class="border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="p-6">
            <div class="flex items-center gap-4">
              <div class="p-3 rounded-lg bg-blue-500/10">
                <Users class="h-6 w-6 text-blue-600" />
              </div>
              <div>
                <div class="text-2xl font-bold text-blue-600">{{ stats.total_referrals }}</div>
                <div class="text-sm text-muted-foreground">Total Referrals</div>
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
                <div class="text-2xl font-bold text-green-600">{{ stats.total_referrer_credits_formatted }}</div>
                <div class="text-sm text-muted-foreground">Credits Awarded</div>
              </div>
            </div>
          </div>
        </Card>

        <Card class="border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="p-6">
            <div class="flex items-center gap-4">
              <div class="p-3 rounded-lg" :class="stats?.settings?.is_enabled ? 'bg-green-500/10' : 'bg-red-500/10'">
                <Gift class="h-6 w-6" :class="stats?.settings?.is_enabled ? 'text-green-600' : 'text-red-600'" />
              </div>
              <div>
                <div class="text-2xl font-bold" :class="stats?.settings?.is_enabled ? 'text-green-600' : 'text-red-600'">
                  {{ stats?.settings?.is_enabled ? 'Active' : 'Disabled' }}
                </div>
                <div class="text-sm text-muted-foreground">System Status</div>
              </div>
            </div>
          </div>
        </Card>
      </div>

      <Tabs v-model="activeTab" class="w-full">
        <TabsList class="grid w-full grid-cols-2 bg-muted/30 border border-border/50">
          <TabsTrigger value="codes">
            <QrCode class="h-4 w-4 mr-2" />
            Referral Codes
          </TabsTrigger>
          <TabsTrigger value="settings">
            <Settings class="h-4 w-4 mr-2" />
            Settings
          </TabsTrigger>
        </TabsList>

        <!-- Codes Tab -->
        <TabsContent value="codes" class="mt-4">
          <Card class="border-2 shadow-xl bg-card/50 backdrop-blur-sm">
            <div class="p-6">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Referral Codes</h3>
                <Badge variant="secondary" class="text-lg px-4 py-2">
                  {{ codesTotal }} Total
                </Badge>
              </div>

              <div v-if="loadingCodes && codes.length === 0" class="flex items-center justify-center py-12">
                <Loader2 class="h-8 w-8 animate-spin" />
              </div>

              <div v-else-if="codes.length === 0" class="text-center py-12 text-muted-foreground">
                No referral codes found
              </div>

              <div v-else class="space-y-2">
                <div
                  v-for="code in codes"
                  :key="code.id"
                  class="flex items-center justify-between p-4 border rounded-lg hover:bg-accent transition-colors"
                >
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                      <Badge variant="outline" class="font-mono text-base">
                        {{ code.code }}
                      </Badge>
                      <Badge :class="code.is_valid ? 'bg-green-500/10 text-green-600' : 'bg-red-500/10 text-red-600'">
                        {{ code.is_valid ? 'Active' : 'Inactive' }}
                      </Badge>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                      <div>
                        <span class="text-muted-foreground">User:</span>
                        <span class="ml-2 font-medium">{{ code.username || code.email || 'Unknown' }}</span>
                      </div>
                      <div>
                        <span class="text-muted-foreground">Uses:</span>
                        <span class="ml-2 font-medium">{{ code.usage_count }} / {{ code.max_uses || '∞' }}</span>
                      </div>
                      <div>
                        <span class="text-muted-foreground">Created:</span>
                        <span class="ml-2 font-medium">{{ formatDate(code.created_at) }}</span>
                      </div>
                    </div>
                  </div>
                  <div class="flex gap-2 ml-4">
                    <Button
                      @click="loadCodeUsage(code)"
                      variant="outline"
                      size="sm"
                    >
                      <Users class="h-4 w-4 mr-2" />
                      View
                    </Button>
                  </div>
                </div>
              </div>

              <!-- Pagination -->
              <div v-if="Math.ceil(codesTotal / 20) > 1" class="flex items-center justify-center gap-2 mt-6">
                <Button
                  @click="loadCodes(codesPage - 1)"
                  :disabled="codesPage === 1"
                  variant="outline"
                  size="sm"
                >
                  <ChevronLeft class="h-4 w-4" />
                </Button>
                <span class="text-sm text-muted-foreground">
                  Page {{ codesPage }} of {{ Math.ceil(codesTotal / 20) }} ({{ codesTotal }} total)
                </span>
                <Button
                  @click="loadCodes(codesPage + 1)"
                  :disabled="codesPage >= Math.ceil(codesTotal / 20)"
                  variant="outline"
                  size="sm"
                >
                  <ChevronRight class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </Card>
        </TabsContent>

        <!-- Settings Tab -->
        <TabsContent value="settings" class="mt-4">
          <Card class="border-2 shadow-xl bg-card/50 backdrop-blur-sm">
            <div class="p-6">
              <div v-if="!settings" class="flex items-center justify-center py-12">
                <Loader2 class="h-8 w-8 animate-spin" />
              </div>

              <form v-else @submit.prevent="saveSettings" class="space-y-6">
                <!-- Enable Toggle -->
                <div class="flex items-center justify-between p-4 rounded-lg bg-muted/30 border border-border/50">
                  <div>
                    <Label class="text-base font-semibold">Enable Referral System</Label>
                    <p class="text-sm text-muted-foreground">Allow users to refer friends</p>
                  </div>
                  <button
                    type="button"
                    role="switch"
                    :aria-checked="settings.is_enabled"
                    @click="settings.is_enabled = !settings.is_enabled"
                    :class="[
                      'relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background',
                      settings.is_enabled ? 'bg-primary' : 'bg-muted',
                    ]"
                  >
                    <span
                      class="pointer-events-none block h-5 w-5 rounded-full bg-white shadow-lg ring-0 transition-transform"
                      :class="settings.is_enabled ? 'translate-x-5' : 'translate-x-0.5'"
                    />
                  </button>
                </div>

                <!-- Credits Settings -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <Label for="referrer_credits">Credits for Referrer</Label>
                    <Input
                      id="referrer_credits"
                      v-model.number="settings.referrer_credits"
                      type="number"
                      min="0"
                      class="mt-2"
                    />
                    <p class="text-sm text-muted-foreground mt-1">Awarded to the user who invited</p>
                  </div>

                  <div>
                    <Label for="referee_credits">Credits for Referee</Label>
                    <Input
                      id="referee_credits"
                      v-model.number="settings.referee_credits"
                      type="number"
                      min="0"
                      class="mt-2"
                    />
                    <p class="text-sm text-muted-foreground mt-1">Awarded to the new user who signed up</p>
                  </div>
                </div>

                <!-- Other Settings -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <Label for="default_max_uses">Default Max Uses</Label>
                    <Input
                      id="default_max_uses"
                      v-model.number="settings.default_max_uses"
                      type="number"
                      min="0"
                      class="mt-2"
                    />
                    <p class="text-sm text-muted-foreground mt-1">0 = Unlimited uses per code</p>
                  </div>

                  <div>
                    <Label for="cookie_lifetime_days">Cookie Lifetime (Days)</Label>
                    <Input
                      id="cookie_lifetime_days"
                      v-model.number="settings.cookie_lifetime_days"
                      type="number"
                      min="1"
                      class="mt-2"
                    />
                    <p class="text-sm text-muted-foreground mt-1">How long referral cookies last</p>
                  </div>
                </div>

                <!-- Custom Codes Toggle -->
                <div class="flex items-center justify-between p-4 rounded-lg bg-muted/30 border border-border/50">
                  <div>
                    <Label class="text-base font-semibold">Allow Custom Referral Codes</Label>
                    <p class="text-sm text-muted-foreground">Let users set their own custom codes</p>
                  </div>
                  <button
                    type="button"
                    role="switch"
                    :aria-checked="settings.allow_custom_codes"
                    @click="settings.allow_custom_codes = !settings.allow_custom_codes"
                    :class="[
                      'relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background',
                      settings.allow_custom_codes ? 'bg-primary' : 'bg-muted',
                    ]"
                  >
                    <span
                      class="pointer-events-none block h-5 w-5 rounded-full bg-white shadow-lg ring-0 transition-transform"
                      :class="settings.allow_custom_codes ? 'translate-x-5' : 'translate-x-0.5'"
                    />
                  </button>
                </div>

                <div class="flex justify-end pt-4 border-t">
                  <Button type="submit" :disabled="savingSettings">
                    <Loader2 v-if="savingSettings" class="h-4 w-4 mr-2 animate-spin" />
                    <Save v-else class="h-4 w-4 mr-2" />
                    Save Settings
                  </Button>
                </div>
              </form>
            </div>
          </Card>
        </TabsContent>
      </Tabs>

      <!-- Code Usage Modal -->
      <div
        v-if="showUsage && selectedCode"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        @click.self="closeUsage"
      >
        <Card class="w-full max-w-2xl m-4 max-h-[80vh] overflow-auto border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="p-6">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-lg font-semibold">Code Usage</h3>
                <p class="text-sm text-muted-foreground">{{ selectedCode.code }}</p>
              </div>
              <Button @click="closeUsage" variant="ghost" size="sm">
                <X class="h-4 w-4" />
              </Button>
            </div>

            <div v-if="loadingUsage && codeUsage.length === 0" class="flex items-center justify-center py-12">
              <Loader2 class="h-8 w-8 animate-spin" />
            </div>

            <div v-else-if="codeUsage.length === 0" class="text-center py-12 text-muted-foreground">
              No users yet
            </div>

            <div v-else class="space-y-2">
              <div
                v-for="user in codeUsage"
                :key="user.id"
                class="flex items-center justify-between p-4 border rounded-lg"
              >
                <div class="flex items-center gap-3">
                  <div class="p-2 rounded-lg bg-green-500/10">
                    <CheckCircle2 class="h-4 w-4 text-green-600" />
                  </div>
                  <div>
                    <div class="font-medium">{{ user.username || user.email || 'Unknown' }}</div>
                    <div class="text-sm text-muted-foreground">Joined {{ formatDate(user.created_at) }}</div>
                  </div>
                </div>
                <Badge class="bg-green-500/10 text-green-600">
                  +{{ settings?.referrer_credits || 0 }} credits
                </Badge>
              </div>
            </div>

            <!-- Pagination -->
            <div v-if="Math.ceil(usageTotal / 20) > 1" class="flex items-center justify-center gap-2 mt-6">
              <Button
                @click="loadCodeUsage(selectedCode!, usagePage - 1)"
                :disabled="usagePage === 1"
                variant="outline"
                size="sm"
              >
                <ChevronLeft class="h-4 w-4" />
              </Button>
              <span class="text-sm text-muted-foreground">
                Page {{ usagePage }} of {{ Math.ceil(usageTotal / 20) }} ({{ usageTotal }} total)
              </span>
              <Button
                @click="loadCodeUsage(selectedCode!, usagePage + 1)"
                :disabled="usagePage >= Math.ceil(usageTotal / 20)"
                variant="outline"
                size="sm"
              >
                <ChevronRight class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </Card>
      </div>
    </div>
  </div>
</template>
