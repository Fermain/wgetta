<script lang="ts">
  import { Accordion } from 'bits-ui'
  import type { Plan, CommandOption, PlanRule } from './lib/api/mock'
  import { MockApi } from './lib/api/mock'

  let step: 'discover' | 'rules' | 'manual' | 'run' | 'deploy' = 'discover'

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

  <Accordion.Root type="single" collapsible value={step} on:change={(e:any)=> step = e.detail.value}>
    <Accordion.Item value="discover">
      <Accordion.Header>
        <Accordion.Trigger>1) Discover</Accordion.Trigger>
      </Accordion.Header>
      <Accordion.Content>
        <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
          <div>
            <label>Base URL</label>
            <input type="text" value={baseUrls[0]} on:input={(e:any)=> baseUrls=[e.currentTarget.value]} style="min-width:360px;" />
          </div>
          <div>
            <label>Options</label>
            <div>
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
        <div style="margin-top:10px;">
          <button on:click={doDiscover}>Run Discover (mock)</button>
        </div>
      </Accordion.Content>
    </Accordion.Item>

    <Accordion.Item value="rules" disabled={!plan}>
      <Accordion.Header>
        <Accordion.Trigger>2) Rules</Accordion.Trigger>
      </Accordion.Header>
      <Accordion.Content>
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
          <div style="margin-top:8px;">
            <button on:click={applyRulesAndContinue}>Continue to Manual</button>
          </div>
        </div>
      </Accordion.Content>
    </Accordion.Item>

    <Accordion.Item value="manual" disabled={!plan}>
      <Accordion.Header>
        <Accordion.Trigger>3) Manual</Accordion.Trigger>
      </Accordion.Header>
      <Accordion.Content>
        <p>Manual selection UI placeholder (virtualized tree to come). Sample:</p>
        <ul>
          {#each sample.slice(0,10) as u}
            <li><code>{u}</code></li>
          {/each}
        </ul>
        <div style="margin-top:8px;">
          <button on:click={gotoRun}>Continue to Run</button>
        </div>
      </Accordion.Content>
    </Accordion.Item>

    <Accordion.Item value="run" disabled={!plan}>
      <Accordion.Header>
        <Accordion.Trigger>4) Run</Accordion.Trigger>
      </Accordion.Header>
      <Accordion.Content>
        <p>Run/Log placeholder.</p>
        <div style="margin-top:8px;">
          <button on:click={gotoDeploy}>Continue to Deploy</button>
        </div>
      </Accordion.Content>
    </Accordion.Item>

    <Accordion.Item value="deploy" disabled={!plan}>
      <Accordion.Header>
        <Accordion.Trigger>5) Deploy</Accordion.Trigger>
      </Accordion.Header>
      <Accordion.Content>
        <p>Deploy placeholder.</p>
      </Accordion.Content>
    </Accordion.Item>
  </Accordion.Root>
</main>
