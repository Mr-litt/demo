# -*- coding: UTF-8 -*-
import sys
sys.path.append('gen-py')

from Services.UserService import UserService
from Services.UserService.ttypes import ErrCodeEnum, UserInfo, UserListResp, ApiErrorException

from thrift.transport import TSocket
from thrift.transport import TTransport
from thrift.protocol import TBinaryProtocol
from thrift.server import TServer

class UserInfoHandler:
    def __init__(self):
        pass

    def get_user_by_uid_list(self, uid_list):
        all_user = {
            1: {
                'uid':1,
                'name': 'NO1',
                'sex':1,
                'age':18,
                'nick': 'nick1'
            },
            2: {
                'uid':2,
                'name': 'NO2',
                'sex':2,
                'age':19,
                'nick': 'nick2'
            },
        }

        user_list = []
        for uid in uid_list:
            if all_user.has_key(uid):
                user = all_user[uid]
                user_info = UserInfo(user['uid'], user['name'], user['sex'], user['age'], user['nick'])
                user_list.append(user_info)
        return user_list

    def userList(self, req):

        try:
            uid_list = req.uidList
            if not uid_list:
                raise Exception('参数错误', ErrCodeEnum.PARAM_ERROR)

            user_list_all = self.get_user_by_uid_list(uid_list)
            if not user_list_all:
                raise Exception('服务器错误', ErrCodeEnum.SERVER_ERROR)

            user_list_resp = UserListResp(user_list_all)
            return user_list_resp
        except Exception as e:
            print e
            raise ApiErrorException(e.getCode(), e.getMessage())

if __name__ == '__main__':
    handler = UserInfoHandler()
    processor = UserService.Processor(handler)
    transport = TSocket.TServerSocket(host='127.0.0.1', port=9090)
    tfactory = TTransport.TBufferedTransportFactory()
    pfactory = TBinaryProtocol.TBinaryProtocolFactory()

    # 简单服务器模式
    server = TServer.TSimpleServer(processor, transport, tfactory, pfactory)
    # 线程模式
    # server = TServer.TThreadedServer(
    #     processor, transport, tfactory, pfactory)

    print "Starting thrift server in python..."
    server.serve()
    print "done!"