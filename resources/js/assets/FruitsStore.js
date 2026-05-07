import { defineStore } from 'pinia';
import axios from 'axios';

export const useFruitsStore = defineStore('fruitsStore',{

    state:()=>({
        list:null,
    }),
    actions:{
        async getList(){
            await axios.get('/api/list')
            .then((res)=>{
                this.list = res.data;
            });
        },
    },

});