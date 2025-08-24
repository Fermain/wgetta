<script lang="ts">
  import { Tabs } from 'bits-ui'
  import WgettaStep from './lib/WgettaStep.svelte'

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
      <WgettaStep title="Discover">
        <div class="w-full grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
          <div>
            <label for="baseurl">Base URL</label>
            <input id="baseurl" type="text" class="min-w-[360px]" placeholder="https://example.com/" />
            <div class="mt-3 prose prose-invert max-w-none">
              <p class="m-0"><strong>Effective Commands (preview)</strong></p>
              <pre class="whitespace-pre-wrap overflow-auto">discover: wget --spider -nv --recursive --level=2 https://example.com/</pre>
              <pre class="whitespace-pre-wrap overflow-auto">run: wget -e robots=off --force-directories https://example.com/</pre>
            </div>
          </div>
          <div>
            <label for="opts">Options</label>
            <div id="opts">
              <div class="mb-1.5"><code>-nv</code></div>
              <div class="mb-1.5"><code>--recursive</code></div>
              <div class="mb-1.5"><code>--level</code> = <input type="text" class="w-24" value="2" /></div>
            </div>
          </div>
        </div>
        <div class="mt-2 flex gap-2">
          <button type="button" class="px-3 py-2 rounded border border-neutral-700">Learn more</button>
          <button on:click={next} class="px-3 py-2 rounded border border-neutral-700">Next</button>
        </div>
      </WgettaStep>
    </Tabs.Content>

    <Tabs.Content value="rules">
      <WgettaStep title="Rules">
        <div>
          <div class="mb-2 flex gap-2">
            <button type="button" class="px-3 py-2 rounded border border-neutral-700">Add pattern</button>
            <button type="button" class="px-3 py-2 rounded border border-neutral-700">Test patterns</button>
          </div>
          <div class="space-y-2">
            <div class="flex gap-2 items-center">
              <input type="checkbox" checked />
              <input type="text" placeholder="POSIX regex" class="min-w-[360px]" />
              <input type="text" placeholder="note" />
            </div>
            <div class="flex gap-2 items-center">
              <input type="checkbox" />
              <input type="text" placeholder="^https?://example.com/wp-admin/" class="min-w-[360px]" />
              <input type="text" placeholder="note" />
            </div>
          </div>
          <div class="mt-2 flex gap-2">
            <button on:click={prev} class="px-3 py-2 rounded border border-neutral-700">Back</button>
            <button on:click={next} class="px-3 py-2 rounded border border-neutral-700">Continue to Manual</button>
          </div>
        </div>
      </WgettaStep>
    </Tabs.Content>

    <Tabs.Content value="manual">
      <WgettaStep title="Manual">
        <p>Review and toggle items to include in the plan.</p>
        <ul class="list-disc pl-6 space-y-1">
          <li><code>https://example.com/</code></li>
          <li><code>https://example.com/about/</code></li>
          <li><code>https://example.com/blog/</code></li>
        </ul>
        <div class="mt-2 flex gap-2">
          <button on:click={prev} class="px-3 py-2 rounded border border-neutral-700">Back</button>
          <button on:click={next} class="px-3 py-2 rounded border border-neutral-700">Continue to Run</button>
        </div>
      </WgettaStep>
    </Tabs.Content>

    <Tabs.Content value="run">
      <WgettaStep title="Run">
        <p>Execution log:</p>
        <pre class="whitespace-pre-wrap overflow-auto h-40 border border-neutral-800 rounded p-2">Starting...
Queued job...
Downloading ...
Done.</pre>
        <div class="mt-2 flex gap-2">
          <button on:click={prev} class="px-3 py-2 rounded border border-neutral-700">Back</button>
          <button on:click={next} class="px-3 py-2 rounded border border-neutral-700">Continue to Deploy</button>
        </div>
      </WgettaStep>
    </Tabs.Content>

    <Tabs.Content value="deploy">
      <WgettaStep title="Deploy">
        <p>Select a completed run and push to GitLab.</p>
        <div class="mt-2 flex gap-2">
          <button on:click={prev} class="px-3 py-2 rounded border border-neutral-700">Back</button>
          <button type="button" class="px-3 py-2 rounded border border-neutral-700">Open Git settings</button>
        </div>
      </WgettaStep>
    </Tabs.Content>
  </Tabs.Root>
</main>
