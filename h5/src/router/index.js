import { createRouter, createWebHashHistory } from 'vue-router'
import { getToken } from '../utils/auth'

const routes = [
    {
        path: '/',
        component: () => import('../views/ArticleList.vue'),
    },
    {
        path: '/login',
        component: () => import('../views/Login.vue'),
    },
    {
        path: '/register',
        component: () => import('../views/Register.vue'),
    },
    {
        path: '/articles/:id',
        component: () => import('../views/ArticleDetail.vue'),
    },
    {
        path: '/profile',
        component: () => import('../views/Profile.vue'),
        meta: { auth: true },
    },
    {
        path: '/profile/edit',
        component: () => import('../views/ProfileEdit.vue'),
        meta: { auth: true },
    },
    {
        path: '/membership',
        component: () => import('../views/MembershipApply.vue'),
    },
]

const router = createRouter({
    history: createWebHashHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach((to, from, next) => {
    const token = getToken()
    if (to.meta.auth && !token) {
        next('/login')
    } else if (to.path === '/login' && token) {
        next('/')
    } else {
        next()
    }
})

export default router
