import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import Login from '../Login.vue'
import { storage } from '@/utils/storage'

vi.mock('axios')

const mountLogin = () =>
  mount(Login, {
    global: {
      stubs: {
        Keyboard: true,
      },
    },
  })

describe('Login.vue', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.clearAllMocks()
    delete window.location
    window.location = { href: '' }
  })

  it('shows an error when submitting without credentials', async () => {
    const wrapper = mountLogin()

    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.text()).toContain('Veuillez entrer votre identifiant et mot de passe.')
    expect(axios.post).not.toHaveBeenCalled()
  })

  it('logs in successfully and stores auth data', async () => {
    axios.post.mockResolvedValueOnce({
      data: { token: 'abc123', user: { id: 1, name: 'Admin' } },
    })

    const wrapper = mountLogin()

    await wrapper.find('#email').setValue('admin@igp.com')
    await wrapper.find('#password').setValue('password')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(axios.post).toHaveBeenCalledWith(expect.stringContaining('/login'), {
      email: 'admin@igp.com',
      password: 'password',
    })
    expect(storage.getAuth().token).toBe('abc123')
    expect(window.location.href).toBe('/dashboard')
  })

  it('shows "Identifiants incorrects" on 401 response', async () => {
    axios.post.mockRejectedValueOnce({ response: { status: 401 } })

    const wrapper = mountLogin()

    await wrapper.find('#email').setValue('admin@igp.com')
    await wrapper.find('#password').setValue('wrong')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('Identifiants incorrects')
  })

  it('shows a generic error on server failure', async () => {
    axios.post.mockRejectedValueOnce(new Error('network error'))

    const wrapper = mountLogin()

    await wrapper.find('#email').setValue('admin@igp.com')
    await wrapper.find('#password').setValue('password')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('Erreur de connexion au serveur')
  })
})
