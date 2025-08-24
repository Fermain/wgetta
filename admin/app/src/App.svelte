<script lang="ts">
  import { Tabs, TabsList, TabsTrigger, TabsContent } from '$lib/components/ui/tabs/index.js'
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
    rules: 'Refine',
    manual: 'Manual',
    run: 'Run',
    deploy: 'Deploy'
  }
</script>

<main class="max-w-screen-lg mx-auto">
  <h1>Wgetta</h1>

  <Tabs bind:value={step}>
    <TabsList>
      {#each steps as s, i}
        <TabsTrigger
          value={s}
          disabled={i > stepIndex()+1}
        >{labels[s]}</TabsTrigger>
      {/each}
    </TabsList>
    <div class="h-1.5 bg-neutral-800 rounded-md my-2">
      <div class="h-1.5 bg-neutral-500 rounded-md"></div>
    </div>

    <TabsContent value="discover">
      <DiscoverStep onNext={next} />
    </TabsContent>

    <TabsContent value="rules">
      <RulesStep onBack={prev} onNext={next} />
    </TabsContent>

    <TabsContent value="manual">
      <ManualStep onBack={prev} onNext={next} />
    </TabsContent>

    <TabsContent value="run">
      <RunStep onBack={prev} onNext={next} />
    </TabsContent>

    <TabsContent value="deploy">
      <DeployStep onBack={prev} />
    </TabsContent>
  </Tabs>
</main>
