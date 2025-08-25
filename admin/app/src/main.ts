import { mount } from 'svelte'
import './app.css'
import App from './App.svelte'

const container = document.getElementById('app')
if (container) {
  mount(App, { target: container })
} else {
  // Fallback: create container if missing (admin pages sometimes render late)
  const el = document.createElement('div')
  el.id = 'app'
  document.body.appendChild(el)
  mount(App, { target: el })
}

export default app
