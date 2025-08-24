<script lang="ts">
  export let onNext: (() => void) | undefined
  import WgettaStep from '../WgettaStep.svelte'
</script>

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
    <button type="button" class="px-3 py-2 rounded border border-neutral-700" on:click={() => onNext?.()}>Next</button>
  </div>
</WgettaStep>


