package thrift;

import Services.UserService.UserService;
import org.apache.thrift.TProcessor;
import org.apache.thrift.protocol.TBinaryProtocol;
import org.apache.thrift.server.TServer;
import org.apache.thrift.server.TSimpleServer;
import org.apache.thrift.transport.TServerSocket;
import org.apache.thrift.transport.TTransportException;

public class Service {
    public static void main(String[] args) {
        try {
            System.out.println("服务端开启....");
            TProcessor tprocessor = new UserService.Processor<UserService.Iface>(new UserServiceImpl());
            TServerSocket serverTransport = new TServerSocket(9090);
            TServer.Args tArgs = new TServer.Args(serverTransport);
            tArgs.processor(tprocessor);
            tArgs.protocolFactory(new TBinaryProtocol.Factory());
            // 简单的单线程服务模型，一般用于测试
            TServer server = new TSimpleServer(tArgs);
            // 线程池服务模型，使用标准的阻塞式IO，预先创建一组线程处理请求。
            //TServer server = new TThreadPoolServer(ttpsArgs);
            // 使用非阻塞式IO，服务端和客户端需要指定TFramedTransport数据传输的方式
            //tArgs.transportFactory(new TFramedTransport.Factory());
            //tArgs.protocolFactory(new TCompactProtocol.Factory());
            //TServer server = new TNonblockingServer(tnbArgs);
            server.serve();
        }catch (TTransportException e) {
            e.printStackTrace();
        }
    }
}
