<script lang="ts">
  import { Tabs } from 'bits-ui'
  import DiscoverStep from './lib/steps/DiscoverStep.svelte'
  import RulesStep from './lib/steps/RulesStep.svelte'
  import ManualStep from './lib/steps/ManualStep.svelte'
  import RunStep from './lib/steps/RunStep.svelte'
  import DeployStep from './lib/steps/DeployStep.svelte'

  const steps = ['discover','rules','manual','run','deploy'] as const
  let step: (typeof steps)[number] = 'discover'
  const stepIndex = () => steps.indexOf(step)
  function next(){ if (stepIndex() < steps.length - 1) step = steps[stepIndex()+1] }
  function prev(){ if (stepIndex() > 0) step = steps[stepIndex()-1] }

  const labels: Record<(typeof steps)[number], string> = {
    discover: 'Discover',
    rules: 'Rules',
    manual: 'Manual',
    run: 'Run',
    deploy: 'Deploy'
  }
</script>

<main class="max-w-screen-lg mx-auto">
  <h1>Wgetta</h1>

  <Tabs.Root bind:value={step}>
    <Tabs.List class="grid grid-cols-5 gap-2 my-2">
      {#each steps as s, i}
        <Tabs.Trigger
          value={s}
          disabled={i > stepIndex()+1}
          class="px-3 py-2 rounded border border-neutral-800 text-sm text-neutral-400 bg-neutral-950 hover:bg-neutral-900 focus:outline-none focus:ring-2 focus:ring-neutral-600 disabled:opacity-40 data-[state=active]:bg-neutral-800 data-[state=active]:border-neutral-500 data-[state=active]:text-white"
        >{labels[s]}</Tabs.Trigger>
      {/each}
    </Tabs.List>
    <div class="h-1.5 bg-neutral-800 rounded-md my-2">
      <div class="h-1.5 bg-neutral-500 rounded-md" style={`width:${((stepIndex()+1)/steps.length)*100}%`}></div>
    </div>

    <Tabs.Content value="discover">
      <DiscoverStep onNext={next} />
    </Tabs.Content>

    <Tabs.Content value="rules">
      <RulesStep onBack={prev} onNext={next} />
    </Tabs.Content>

    <Tabs.Content value="manual">
      <ManualStep onBack={prev} onNext={next} />
    </Tabs.Content>

    <Tabs.Content value="run">
      <RunStep onBack={prev} onNext={next} />
    </Tabs.Content>

    <Tabs.Content value="deploy">
      <DeployStep onBack={prev} />
    </Tabs.Content>
  </Tabs.Root>
</main>
