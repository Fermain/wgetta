export type CommandOption = { name: string; value: string | null; editable: boolean };
export type PlanRule = { id: string; pattern: string; type: 'posix'; enabled: boolean; note?: string };
export type PlanUrl = { url: string; included: boolean; source: 'discover'|'rule'|'manual'; matchedRuleIds?: string[]; note?: string };
export type Plan = {
  schemaVersion: 1;
  id: string;
  name: string;
  createdAt: number;
  updatedAt: number;
  command: { baseUrls: string[]; options: CommandOption[] };
  rules: PlanRule[];
  urls: PlanUrl[];
  stats: { total: number; included: number; excluded: number };
};

export type JobSummary = { id: string; status: string; files?: number; bytes?: number; elapsed_seconds?: number; zip_url?: string };

const delay = (ms: number) => new Promise((r) => setTimeout(r, ms));

export const MockApi = {
  async discover(command: { baseUrls: string[]; options: CommandOption[] }): Promise<{ plan: Plan; sample: string[] }> {
    await delay(400);
    const now = Math.floor(Date.now() / 1000);
    const urls = Array.from({ length: 150 }, (_, i) => ({
      url: `${command.baseUrls[0]}path/${i}/`,
      included: true,
      source: 'discover' as const
    }));
    const plan: Plan = {
      schemaVersion: 1,
      id: `plan-${now}`,
      name: `Plan ${now}`,
      createdAt: now,
      updatedAt: now,
      command,
      rules: [],
      urls,
      stats: { total: urls.length, included: urls.length, excluded: 0 }
    };
    return { plan, sample: urls.slice(0, 20).map((u) => u.url) };
  },

  async testRules(rules: PlanRule[], sample: string[]): Promise<{ includedCount: number; excludedCount: number; matches: Record<string, string[]> }> {
    await delay(200);
    const matches: Record<string, string[]> = {};
    const enabled = rules.filter((r) => r.enabled);
    const excludedSet = new Set<string>();
    for (const url of sample) {
      const hitIds: string[] = [];
      for (const r of enabled) {
        try {
          const rx = new RegExp(r.pattern);
          if (rx.test(url)) hitIds.push(r.id);
        } catch (_) {}
      }
      if (hitIds.length > 0) {
        matches[url] = hitIds;
        excludedSet.add(url);
      }
    }
    const excludedCount = excludedSet.size;
    const includedCount = sample.length - excludedCount;
    return { includedCount, excludedCount, matches };
  },

  async savePlan(plan: Plan): Promise<Plan> {
    await delay(150);
    return { ...plan, updatedAt: Math.floor(Date.now() / 1000) };
  },

  async runPlan(planId: string): Promise<{ jobId: string }> {
    await delay(200);
    return { jobId: `job_${planId}` };
  },

  async jobLog(jobId: string, offset = 0): Promise<{ content: string; offset: number; status: { status: string } }> {
    await delay(300);
    const content = offset === 0 ? 'Starting job...\n' : 'Working...\n';
    const status = { status: offset > 2000 ? 'completed' : 'running' };
    return { content, offset: offset + content.length, status };
  },

  async recentJobs(): Promise<JobSummary[]> {
    await delay(120);
    return [];
  }
};


