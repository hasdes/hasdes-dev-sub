<script setup>
import {reactive} from 'vue'
import axios from 'axios';
import {ElNotification} from 'element-plus'
import { trim } from 'lodash';

const form=reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const validate=()=>{

    console.log('email '+form.email);

    if(trim(form.name)==''||trim(form.email)==''||trim(form.password)==''){
        ElNotification({
            title: 'Error',
            message: 'name,e-mail,passwordは省略できません',
            type: 'error',
        })
        return false;
    }

    if(form.password!=form.password_confirmation){
        ElNotification({
        title: 'Error',
        message: 'パスワードの入力に誤りがあります',
        type: 'error',
      })
        return false;
    }

    return true;
}

const create=()=>{
    if(!validate())return;
    axios.post('/api/auth/create',form)
    .then((res)=>{
      ElNotification({
        title: 'Success',
        message: '登録成功しました',
        type: 'success',
      })
    })
    .catch((error)=>{
        console.log('error '+error);
        ElNotification({
        title: 'Error',
        message: '登録に失敗しました',
        type: 'error',
      });
    });
}

</script>
<template>
    <el-card>
    <el-form :model="form">
        <el-form-item label="name" :label-width="140">
            <el-input v-model="form.name" autocomplete="off" />
        </el-form-item>
        <el-form-item label="e-mail" :label-width="140">
            <el-input v-model="form.email" autocomplete="off" />
        </el-form-item>
        <el-form-item label="password" :label-width="140" >
            <el-input v-model="form.password" autocomplete="new-password" type="password"/>
        </el-form-item>
        <el-form-item label="re:password" :label-width="140" >
            <el-input v-model="form.password_confirmation" autocomplete="off" type="password"/>
        </el-form-item>
    </el-form>
    <div align="right">
        <el-button @click="create" type="primary">作成</el-button>
    </div>
    </el-card>
</template>