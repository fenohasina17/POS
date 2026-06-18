import { describe, it, expect, beforeEach } from 'vitest'
import { storage } from '../storage'

describe('storage', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  describe('auth', () => {
    it('stores and retrieves the token and user with roles/permissions', () => {
      storage.setAuth('abc123', { id: 1, name: 'Admin' }, ['admin'], ['view.sales'])

      const auth = storage.getAuth()

      expect(auth.token).toBe('abc123')
      expect(auth.user).toMatchObject({
        id: 1,
        name: 'Admin',
        roles: ['admin'],
        permissions: ['view.sales'],
      })
    })

    it('returns null token/user when nothing is stored', () => {
      expect(storage.getAuth()).toEqual({ token: null, user: null })
    })

    it('returns null user when stored value is the literal string "undefined"', () => {
      localStorage.setItem('token', 'abc123')
      localStorage.setItem('user', 'undefined')

      expect(storage.getAuth()).toEqual({ token: 'abc123', user: null })
    })

    it('removes auth data', () => {
      storage.setAuth('abc123', { id: 1 })
      storage.removeAuth()

      expect(storage.getAuth()).toEqual({ token: null, user: null })
    })

    it('clearAuth is an alias for removeAuth', () => {
      storage.setAuth('abc123', { id: 1 })
      storage.clearAuth()

      expect(storage.getAuth()).toEqual({ token: null, user: null })
    })
  })

  describe('session', () => {
    it('stores and retrieves the cash register session', () => {
      storage.setSession({ id: 42, status: 'open' })

      expect(storage.getSession()).toEqual({ id: 42, status: 'open' })
    })

    it('returns null when no session is stored', () => {
      expect(storage.getSession()).toBeNull()
    })

    it('removes the session', () => {
      storage.setSession({ id: 42 })
      storage.removeSession()

      expect(storage.getSession()).toBeNull()
    })
  })
})
