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
</script>

<main class="max-w-screen-lg mx-auto">
  <h1>Wgetta</h1>
  <p>Discover → Rules → Manual → Run → Deploy</p>

  <Tabs.Root value={step} on:change={(e:any)=> step = e.detail.value}>
    <Tabs.List>
      {#each steps as s, i}
        <Tabs.Trigger value={s} disabled={i > stepIndex()+1}>{i+1}) {s}</Tabs.Trigger>
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
