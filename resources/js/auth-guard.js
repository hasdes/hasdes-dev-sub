import {useLoginState} from "./assets/LoginState.js"

export const authGuard = (router) => {
    const st=useLoginState();
    router.beforeEach((to)=>{
        
        if (['auth.login'].includes(to.name)) {
            return true;
        }

        if(st.login){
            return true;
        };
        
        return {name: 'auth.login'};
    });
};
