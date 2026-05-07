import { defineStore } from 'pinia'

export const useLoginState =defineStore('loginstate',{
    state:()=>({
        login:false,
    }),
    actions:{
        setLogin(){
            this.login=true;
        },
        setLogout(){
            this.login=false;
        },
    },
});
