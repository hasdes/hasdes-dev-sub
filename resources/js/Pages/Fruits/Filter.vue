<script setup>
import {ref,watch } from 'vue'
import {throttle} from 'lodash'

const filterText=ref('');
const emit = defineEmits(['reLoad']);

watch(filterText,
        throttle(()=>{
            if(filterText.value!==''){
                emit('reLoad',filterText.value);
            }else{
                emit('reLoad');
            }
        },150)
);

</script>
<template>
    <el-form-item label="絞り込み" >
        <el-input v-model="filterText" autocomplete="off" />
    </el-form-item>
</template>
