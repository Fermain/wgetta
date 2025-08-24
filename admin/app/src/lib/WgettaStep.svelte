<script lang="ts">
  export let title: string | undefined
</script>

<section class="w-full max-w-[1000px] mx-auto text-left">
  {#if title}
    <h2 class="text-xl font-semibold mb-3">{title}</h2>
  {/if}
  <div class="w-full">
    <slot />
  </div>
</section>


