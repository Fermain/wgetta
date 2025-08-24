<script lang="ts">
  import { Tabs } from 'bits-ui'
  import type { Plan, CommandOption, PlanRule } from './lib/api/mock'
  import { MockApi } from './lib/api/mock'
  import { assembleEffective } from './lib/cmd'
  import WgettaStep from './lib/WgettaStep.svelte'

  const steps = ['discover','rules','manual','run','deploy'] as const
  let step: (typeof steps)[number] = 'discover'
  const stepIndex = () => steps.indexOf(step)
  function next(){ if (stepIndex() < steps.length - 1) step = steps[stepIndex()+1] }
  function prev(){ if (stepIndex() > 0) step = steps[stepIndex()-1] }

  let baseUrls: string[] = [window?.WGETTA?.siteUrl || 'https://example.com/']
  let options: CommandOption[] = [
    { name: '-nv', value: null, editable: true },
    { name: '--recursive', value: null, editable: true },
    { name: '--level', value: '2', editable: true }
  ]

  let plan: Plan | null = null
  let sample: string[] = []
  let rules: PlanRule[] = []
  let testing = false
  let testSummary: { includedCount: number; excludedCount: number } | null = null
  $: eff = assembleEffective({ baseUrls, options })

  async function doDiscover() {
    const res = await MockApi.discover({ baseUrls, options })
    plan = res.plan
    sample = res.sample
    step = 'rules'
  }

  function addRule() {
    const id = 'r' + (rules.length + 1)
    rules = [...rules, { id, pattern: '', type: 'posix', enabled: true }]
  }

  async function testRules() {
    testing = true
    try {
      const res = await MockApi.testRules(rules, sample)
      testSummary = { includedCount: res.includedCount, excludedCount: res.excludedCount }
    } finally {
      testing = false
    }
  }

  async function applyRulesAndContinue() {
    // For mock, just move to manual step
    step = 'manual'
  }

  function gotoRun() {
    step = 'run'
  }

  function gotoDeploy() {
    step = 'deploy'
  }
</script>

<main>
  <h1>Wgetta</h1>
  <p>Discover → Rules → Manual → Run → Deploy</p>

  <Tabs.Root value={step} on:change={(e:any)=> step = e.detail.value}>
    <Tabs.List>
      {#each steps as s, i}
        <Tabs.Trigger value={s} disabled={i > stepIndex()+1}>{i+1}) {s}</Tabs.Trigger>
      {/each}
    </Tabs.List>
    <div style="height:6px; background:#333; border-radius:4px; margin:8px 0;">
      <div style={`height:6px; background:#888; border-radius:4px; width:${((stepIndex()+1)/steps.length)*100}%`}></div>
    </div>

    <Tabs.Content value="discover">
      <WgettaStep title="Discover">
        <div class="w-full" style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; align-items:start;">
          <div>
            <label for="baseurl">Base URL</label>
            <input id="baseurl" type="text" value={baseUrls[0]} on:input={(e:any)=> baseUrls=[e.currentTarget.value]} style="min-width:360px;" />
            <div style="margin-top:12px;">
              <strong>Effective Commands (preview)</strong>
              {#if baseUrls.length}
                <pre style="white-space:pre-wrap; overflow:auto;">discover: {eff.discover.join(' ')}</pre>
                <pre style="white-space:pre-wrap; overflow:auto;">run: {eff.run.join(' ')}</pre>
              {/if}
            </div>
          </div>
          <div>
            <label for="opts">Options</label>
            <div id="opts">
              {#each options as opt, i}
                <div style="margin-bottom:6px;">
                  <code>{opt.name}</code>
                  {#if opt.value !== null}
                    = <input type="text" value={opt.value} on:input={(e:any)=> options[i] = { ...opt, value: e.currentTarget.value }} />
                  {/if}
                  {#if !opt.editable}
                    <span title="locked">🔒</span>
                  {/if}
                </div>
              {/each}
            </div>
          </div>
        </div>
        <div style="margin-top:10px; display:flex; gap:8px;">
          <button on:click={doDiscover}>Run Discover (mock)</button>
          <button on:click={next}>Next</button>
        </div>
      </WgettaStep>
    </Tabs.Content>

    <Tabs.Content value="rules">
      <WgettaStep title="Rules">
        <div>
          <div style="margin-bottom:8px;">
            <button on:click={addRule}>Add Rule</button>
            <button on:click={testRules} disabled={rules.length===0 || testing} style="margin-left:8px;">{testing ? 'Testing…' : 'Test Rules (mock)'}</button>
          </div>
          {#each rules as r, i}
            <div style="display:flex; gap:8px; align-items:center; margin-bottom:6px;">
              <input type="checkbox" checked={r.enabled} on:change={(e:any)=> rules[i] = { ...r, enabled: e.currentTarget.checked }} />
              <input type="text" placeholder="POSIX regex" value={r.pattern} on:input={(e:any)=> rules[i] = { ...r, pattern: e.currentTarget.value }} style="min-width:360px;" />
              <input type="text" placeholder="note" value={r.note || ''} on:input={(e:any)=> rules[i] = { ...r, note: e.currentTarget.value }} />
            </div>
          {/each}
          {#if testSummary}
            <p>Included: {testSummary.includedCount} • Excluded: {testSummary.excludedCount}</p>
          {/if}
          <div style="margin-top:8px; display:flex; gap:8px;">
            <button on:click={prev}>Back</button>
            <button on:click={applyRulesAndContinue}>Continue to Manual</button>
          </div>
        </div>
      </WgettaStep>
    </Tabs.Content>

    <Tabs.Content value="manual">
      <WgettaStep title="Manual">
        <p>Manual selection UI placeholder (virtualized tree to come). Sample:</p>
        <ul>
          {#each sample.slice(0,10) as u}
            <li><code>{u}</code></li>
          {/each}
        </ul>
        <div style="margin-top:8px; display:flex; gap:8px;">
          <button on:click={prev}>Back</button>
          <button on:click={gotoRun}>Continue to Run</button>
        </div>
      </WgettaStep>
    </Tabs.Content>

    <Tabs.Content value="run">
      <WgettaStep title="Run">
        <p>Run/Log placeholder.</p>
        <div style="margin-top:8px; display:flex; gap:8px;">
          <button on:click={prev}>Back</button>
          <button on:click={gotoDeploy}>Continue to Deploy</button>
        </div>
      </WgettaStep>
    </Tabs.Content>

    <Tabs.Content value="deploy">
      <WgettaStep title="Deploy">
        <p>Deploy placeholder.</p>
        <div style="margin-top:8px; display:flex; gap:8px;">
          <button on:click={prev}>Back</button>
        </div>
      </WgettaStep>
    </Tabs.Content>
  </Tabs.Root>
</main>
